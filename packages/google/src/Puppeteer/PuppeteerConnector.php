<?php

namespace PiedWeb\Google\Puppeteer;

class PuppeteerConnector
{
    /**
     * @var array<string, string>
     */
    public static array $wsEndpointList = [];

    /**
     * Public egress IP per proxy, probed once and cached for the process lifetime.
     *
     * @var array<string, string>
     */
    private static array $exitIpCache = [];

    private static string $lastWsEndpointUsed = '';

    /**
     * True when the previous get() encountered a captcha that was successfully solved.
     * Lets callers count captcha-encounters even when extraction succeeded.
     */
    public bool $lastCaptchaSolved = false;

    /**
     * Real wire bytes downloaded by the previous get() (all paginations + subresources),
     * parsed from scrap.js' NETBYTES marker. 0 when the scrape failed or returned a captcha.
     */
    public int $lastTransferBytes = 0;

    public function close(): void
    {
        $id = (string) \Safe\getmypid();
        foreach (static::$wsEndpointList as $key => $wsEndpoint) {
            if (str_starts_with($key, $id)) {
                @exec('PUPPETEER_WS_ENDPOINT='.escapeshellarg($wsEndpoint).' node '.escapeshellarg(__DIR__.'/closeBrowser.js'));
                unset(static::$wsEndpointList[$key]);
            }
        }
    }

    /**
     * @param array<string|int> $args
     */
    public function execute(string $script, array $args = [], int $scrapWait = 1000): string
    {
        $wsEndpoint = $this->getWsEndpoint();

        $outputFileLog = sys_get_temp_dir().'/puppeteer-direct-'.\Safe\getmypid();

        $argsStr = '';
        foreach ($args as $arg) {
            if (! \is_string($arg)) { // so is int
                $argsStr .= ' '.$arg;

                continue;
            }

            $argsStr .= ' '.escapeshellarg($arg);
        }

        $captchaToken = $this->getCaptchaToken();
        $cmd = null !== $captchaToken ? 'PUPPETEER_2CAPTCHA_TOKEN='.escapeshellarg($captchaToken).' ' : '';
        // Credential-auth proxy: scrap.js feeds these to page.authenticate (Chrome launched with the
        // credential-free gate can't authenticate the proxy itself).
        if ('' !== $this->proxyUser) {
            $cmd .= 'PROXY_USER='.escapeshellarg($this->proxyUser).' PROXY_PASS='.escapeshellarg($this->proxyPass).' ';
        }
        $cmd .= 'SCRAP_WAIT='.$scrapWait.' ';
        $cmd .= 'PUPPETEER_WS_ENDPOINT='.escapeshellarg($wsEndpoint).' ';
        $cmd .= 'node '.escapeshellarg($script).' '.$argsStr.' > '.escapeshellarg($outputFileLog);

        // Guard: kill scrap.js if it hangs (e.g. stuck puppeteer.connect on a dead WS endpoint).
        // Wrap in sh -c so env-var prefixes (SCRAP_WAIT=… PUPPETEER_WS_ENDPOINT=…) are parsed by the shell.
        \Safe\exec('timeout 60 sh -c '.escapeshellarg($cmd));
        $rawOutput = \Safe\file_get_contents($outputFileLog); // going with file io to avoid truncated output

        return $rawOutput;
    }

    private function getCaptchaToken(): ?string
    {
        $token = $_SERVER['PUPPETEER_2CAPTCHA_TOKEN'] ?? null;

        return \is_string($token) ? $token : null;
    }

    public function get(string $url, int $maxPages): string
    {
        $this->lastCaptchaSolved = false;
        $this->lastTransferBytes = 0;

        $rawOutput = $this->execute(__DIR__.'/scrap.js', [$url, $maxPages]);

        // scrap.js crashed (e.g. detached frame on Google's #ip=1 infinite-scroll navigation): retry once on a fresh browser
        if ('' === trim($rawOutput)) {
            $this->close();
            $rawOutput = $this->execute(__DIR__.'/scrap.js', [$url, $maxPages]);
        }

        if ('captcha' === trim($rawOutput) && ! $this->isHeadless()) {
            $this->close();
            $_SERVER['PUPPETEER_HEADLESS'] = false;
            $rawOutput = $this->execute(__DIR__.'/scrap.js', [$url, $maxPages], 30000);
            $this->close(); // on referme le brower pour repasser en headless
            $_SERVER['PUPPETEER_HEADLESS'] = true;
        }

        // NETBYTES is the outermost marker (prepended last by scrap.js), so strip it before captcha.
        return $this->stripCaptchaSolvedMarker($this->stripNetBytesMarker($rawOutput));
    }

    private function stripNetBytesMarker(string $rawOutput): string
    {
        // scrap.js prepends this marker, but a stray diagnostic line on stdout could land ahead of
        // it — so match past any leading lines (not only byte 0) and drop everything up to and
        // including the marker. The marker is our own HTML comment, never present in the SERP body.
        if (1 === preg_match('/<!--NETBYTES:(\d+)-->\r?\n/', $rawOutput, $m, \PREG_OFFSET_CAPTURE)) {
            $this->lastTransferBytes = (int) $m[1][0];

            return substr($rawOutput, $m[0][1] + \strlen($m[0][0]));
        }

        return $rawOutput;
    }

    private function stripCaptchaSolvedMarker(string $rawOutput): string
    {
        // Same leading-noise tolerance as stripNetBytesMarker: find the marker anywhere in the
        // small prefix scrap.js prepends (before the <html> document) and strip up to it.
        $marker = "<!--CAPTCHA_SOLVED-->\n";
        $pos = strpos($rawOutput, $marker);
        if (false !== $pos) {
            $this->lastCaptchaSolved = true;

            return substr($rawOutput, $pos + \strlen($marker));
        }

        return $rawOutput;
    }

    public static function screenshot(string $path, string $wsEndpoint = ''): void
    {
        $wsEndpoint = $wsEndpoint ?: self::$lastWsEndpointUsed ?: throw new \Exception();
        $cmd = 'PUPPETEER_WS_ENDPOINT='.escapeshellarg($wsEndpoint).' '
           .'node '.escapeshellarg(__DIR__.'/screenshot.js').' '
           .escapeshellarg($path);

        \Safe\exec($cmd);
    }

    /**
     * @param string $language  if language or proxy are changed, a new chrome will be launched
     * @param string $proxy     credential-free gateway for --proxy-server (Chrome cannot embed creds)
     * @param string $proxyUser proxy username (product/country/session label); scrap.js feeds it to
     *                          page.authenticate — Chrome cannot authenticate a proxy on its own
     * @param string $proxyPass proxy password for page.authenticate
     */
    public function __construct(
        public string $language = 'fr',
        public string $proxy = '',
        public string $proxyUser = '',
        public string $proxyPass = '',
    ) {
    }

    /**
     * Resolve the public egress IP behind the current proxy, probed once per proxy per process.
     *
     * Empty when there is no proxy (direct egress) or when the probe fails — callers then skip
     * IP-keying and fall back to the inherited profile.
     */
    public function resolveExitIp(): string
    {
        if ('' === $this->proxy) {
            return '';
        }

        if (! isset(self::$exitIpCache[$this->proxy])) {
            self::$exitIpCache[$this->proxy] = self::probeExitIp($this->proxy, $this->proxyUser, $this->proxyPass);
        }

        return self::$exitIpCache[$this->proxy];
    }

    /**
     * The proxy Chrome should actually route through. Empty when none is configured OR when its
     * exit IP can't be probed (a DOWN exit) — in both cases the browser launches with direct
     * egress (this host's own IP) rather than through a dead proxy, which would fail every fetch.
     */
    public function effectiveProxy(): string
    {
        return '' !== $this->resolveExitIp() ? $this->proxy : '';
    }

    /**
     * Chrome's --proxy-server rejects socks5h:// (ERR_NO_SUPPORTED_PROXIES) and does proxy-side
     * DNS for socks5 anyway, so map the curl-style socks5h scheme to socks5 for the browser.
     * curl keeps socks5h (for remote DNS); only the browser launch goes through here.
     */
    public static function chromeProxy(string $proxy): string
    {
        return str_starts_with($proxy, 'socks5h://') ? 'socks5://'.substr($proxy, 10) : $proxy;
    }

    private static function probeExitIp(string $proxy, string $proxyUser = '', string $proxyPass = ''): string
    {
        $handle = curl_init('https://api.ipify.org');
        if (false === $handle) {
            return '';
        }

        $opts = [
            \CURLOPT_PROXY => $proxy,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_CONNECTTIMEOUT => 5,
            \CURLOPT_TIMEOUT => 10,
        ];
        // Authenticated commercial gateway: without creds the probe fails and effectiveProxy() would
        // wrongly fall back to direct egress, so the browser would never use the proxy.
        if ('' !== $proxyUser) {
            $opts[\CURLOPT_PROXYUSERPWD] = $proxyUser.':'.$proxyPass;
        }
        curl_setopt_array($handle, $opts);
        $output = curl_exec($handle);

        $ip = \is_string($output) ? trim($output) : '';

        return false !== filter_var($ip, \FILTER_VALIDATE_IP) ? $ip : '';
    }

    private function exitProfileBase(): string
    {
        $base = $_SERVER['PUPPETEER_EXIT_PROFILE_BASE'] ?? null;

        return \is_string($base) && '' !== $base ? $base : sys_get_temp_dir().'/pp-exit-profiles';
    }

    /**
     *  @psalm-suppress NullableReturnStatement
     *  @psalm-suppress InvalidNullableReturnType
     *
     * @return string could be empty if create = false and no endpoint match
     */
    public function getWsEndpoint(bool $create = true): string
    {
        // Bind the browser identity to the egress IP: an IP change yields a new id (fresh
        // browser) and a per-IP profile dir below, so one IP's cf_clearance / Google cookies
        // are never replayed from another IP (a strong bot signal).
        $exitIp = $this->resolveExitIp();
        $proxy = $this->effectiveProxy(); // '' when no proxy or a dead one → direct-egress fallback
        $id = \Safe\getmypid().'-'.$this->language.'-'.$proxy.('' !== $exitIp ? '-'.$exitIp : '');

        if (isset(static::$wsEndpointList[$id])) {
            self::$lastWsEndpointUsed = static::$wsEndpointList[$id];

            return static::$wsEndpointList[$id];
        }

        if (! $create) {
            return '';
        }

        $cmd = '';

        if ('' !== $proxy) {
            $cmd .= 'PROXY_GATE='.escapeshellarg(self::chromeProxy($proxy)).' ';
        }

        // Persistent profile per exit IP (reused when the IP recurs → keeps the warm
        // captcha-cleared session, no re-solve). Distinct dirs also stop launchBrowser's
        // same-userDataDir pkill from killing a concurrent other-IP browser.
        if ('' !== $exitIp) {
            $cmd .= 'PUPPETEER_USER_DATA_DIR='.escapeshellarg($this->exitProfileBase().'/'.$exitIp).' ';
        }

        if (! $this->isHeadless()) {
            $cmd .= 'PUPPETEER_HEADLESS=0 ';
        }

        $safeId = (string) preg_replace('/[^A-Za-z0-9_.-]/', '_', $id);
        $outputFileLog = sys_get_temp_dir().'/puppeteer-direct-'.$safeId;
        $cmd .= 'node '.escapeshellarg(__DIR__.'/launchBrowser.js').' '.escapeshellarg($this->language)
                    .' > '.escapeshellarg($outputFileLog).' 2>&1 &';

        // Chrome occasionally dies mid-startup (a concurrent same-profile launch's pkill, a stale
        // SingletonLock, a cold-start OOM) right after publishing its devtools URL, so
        // puppeteer.launch() throws ECONNREFUSED and launchBrowser.js writes a multi-line error
        // blob instead of a ws:// endpoint. Treat a non-ws:// output as a failed launch and
        // relaunch on a fresh Chrome (the next launch's killExistingBrowserProcesses clears the
        // dead one first), rather than caching the error blob and failing every downstream connect.
        $wsEndpoint = '';
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            @unlink($outputFileLog);
            \Safe\exec($cmd);

            for ($i = 0; $i < 5; ++$i) {
                $output = trim((string) @file_get_contents($outputFileLog));
                if (self::isValidWsEndpoint($output)) {
                    $wsEndpoint = $output;

                    break 2;
                }

                if ('' !== $output) {
                    break; // launch failed (error blob) — relaunch on a fresh Chrome
                }

                sleep(1);
            }
        }

        static::$wsEndpointList[$id] = $wsEndpoint;

        register_shutdown_function([$this, 'close']);
        $this->installSignalTraps();

        self::$lastWsEndpointUsed = $wsEndpoint;

        return $wsEndpoint;
    }

    /**
     * A successful launchBrowser.js prints a single ws:// devtools URL on stdout; a failed one
     * prints an "Error in launchBrowser.js: …" blob. Only the former is a usable endpoint.
     */
    private static function isValidWsEndpoint(string $output): bool
    {
        return str_starts_with($output, 'ws://') || str_starts_with($output, 'wss://');
    }

    /**
     * Ensure close() runs when the worker is killed by SIGTERM/SIGINT/SIGHUP — register_shutdown_function
     * only covers graceful exit, so without this the daemonized launchBrowser.js + Chrome leak forever
     * on Ctrl+C, systemd stop, kill, timeout. SIGKILL/OOM/SEGV are uncatchable; idle leaks self-heal on
     * the next launch via launchBrowserHelper.killExistingBrowserProcesses().
     */
    private function installSignalTraps(): void
    {
        if (! \function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);
        foreach ([\SIGTERM, \SIGINT, \SIGHUP] as $sig) {
            pcntl_signal($sig, function (int $sig): void {
                $this->close();
                pcntl_signal($sig, \SIG_DFL);
                posix_kill(posix_getpid(), $sig);
            });
        }
    }

    private function isHeadless(): bool
    {
        if (! isset($_SERVER['PUPPETEER_HEADLESS'])) {
            return true;
        }

        return ! \in_array($_SERVER['PUPPETEER_HEADLESS'], ['0', 'false', false], true);
    }
}
