/**
 * CapSolver task-shape check for the SERP scraper.
 *
 *   node packages/google/tests/capsolver-task-check.js
 *   composer test:capsolver
 *
 * Regression cover for a captcha that solved but was never counted (430 solves, 0 recorded). Google's
 * /sorry reCAPTCHA is Enterprise: it needs the dedicated CapSolver Enterprise task type AND the
 * site-specific `s` payload. The scraper used to send a plain ReCaptchaV2Task with an `isEnterprise`
 * flag CapSolver ignores, and dropped `s` entirely — so the token was minted as a plain v2 and
 * rejected by the Enterprise interstitial. capSolverTask now maps the four (enterprise × proxy) cases
 * to the right type and forwards `s`.
 *
 * Pure logic: no browser, no network, no CapSolver call. Exit 0 = pass, 1 = a case regressed.
 */

const { capSolverTask } = require('../src/Puppeteer/scrap');

let failures = 0;
function check(label, cond) {
  if (cond) {
    console.log('  ok   ' + label);
  } else {
    console.error('  FAIL ' + label);
    failures++;
  }
}

const PROXY = 'socks5:1.2.3.4:12200:user:pass';
const base = { url: 'https://www.google.com/sorry/index?q=x', sitekey: '6LdLLIMb', id: 'abc123' };

// 1. Enterprise reCAPTCHA through a solver proxy (own-exit / commercial lane): the live /sorry case.
process.env.PROXY_SOLVER = PROXY;
{
  const t = capSolverTask({ ...base, _vendor: 'recaptcha', isEnterprise: true, s: 'SDATA' });
  check('enterprise+proxy → ReCaptchaV2EnterpriseTask', t.type === 'ReCaptchaV2EnterpriseTask');
  check('enterprise+proxy carries the proxy', t.proxy === PROXY);
  check(
    'enterprise+proxy forwards s in enterprisePayload',
    t.enterprisePayload && t.enterprisePayload.s === 'SDATA',
  );
  check('enterprise+proxy never sets isEnterprise flag', !('isEnterprise' in t));
}

// 2. Enterprise reCAPTCHA with no solver proxy (direct egress) → ProxyLess enterprise variant.
delete process.env.PROXY_SOLVER;
delete process.env.PROXY_GATE;
delete process.env.PROXY_USER;
{
  const t = capSolverTask({ ...base, _vendor: 'recaptcha', isEnterprise: true, s: 'SDATA' });
  check(
    'enterprise+proxyless → ReCaptchaV2EnterpriseTaskProxyLess',
    t.type === 'ReCaptchaV2EnterpriseTaskProxyLess',
  );
  check('enterprise+proxyless omits proxy', !('proxy' in t));
  check(
    'enterprise+proxyless still forwards s',
    t.enterprisePayload && t.enterprisePayload.s === 'SDATA',
  );
}

// 3. Enterprise reCAPTCHA missing the s payload → enterprise type, no enterprisePayload (nothing to send).
{
  const t = capSolverTask({ ...base, _vendor: 'recaptcha', isEnterprise: true });
  check(
    'enterprise without s → still enterprise type',
    t.type === 'ReCaptchaV2EnterpriseTaskProxyLess',
  );
  check('enterprise without s → no empty enterprisePayload', !('enterprisePayload' in t));
}

// 4. Plain (non-enterprise) reCAPTCHA → the vanilla v2 task, no enterprisePayload.
process.env.PROXY_SOLVER = PROXY;
{
  const t = capSolverTask({ ...base, _vendor: 'recaptcha', isEnterprise: false, s: 'SDATA' });
  check('plain v2+proxy → ReCaptchaV2Task', t.type === 'ReCaptchaV2Task');
  check('plain v2 carries the proxy', t.proxy === PROXY);
  check('plain v2 sends no enterprisePayload', !('enterprisePayload' in t));
}

// 5. hCaptcha → always ProxyLess, never routed through the solver proxy, never enterprise.
{
  const t = capSolverTask({ ...base, _vendor: 'hcaptcha', isEnterprise: true, s: 'SDATA' });
  check('hcaptcha → HCaptchaTaskProxyLess', t.type === 'HCaptchaTaskProxyLess');
  check('hcaptcha omits proxy', !('proxy' in t));
  check('hcaptcha omits enterprisePayload', !('enterprisePayload' in t));
}

// 6. Plain (non-enterprise) reCAPTCHA with no proxy → the fourth type variant, ReCaptchaV2TaskProxyLess.
delete process.env.PROXY_SOLVER;
{
  const t = capSolverTask({ ...base, _vendor: 'recaptcha', isEnterprise: false });
  check('plain v2+proxyless → ReCaptchaV2TaskProxyLess', t.type === 'ReCaptchaV2TaskProxyLess');
  check('plain v2+proxyless omits proxy', !('proxy' in t));
  check('plain v2+proxyless sends no enterprisePayload', !('enterprisePayload' in t));
}

// 7. Commercial gateway lane (PROXY_GATE + PROXY_USER, no PROXY_SOLVER): capSolverTask must derive the
//    egress proxy from the gate so the token is minted from Chrome's IP — enterprise task, socks5h→socks5.
process.env.PROXY_GATE = 'socks5h://10.0.0.1:9000';
process.env.PROXY_USER = 'cust';
process.env.PROXY_PASS = 'pw';
{
  const t = capSolverTask({ ...base, _vendor: 'recaptcha', isEnterprise: true, s: 'SDATA' });
  check('commercial gate → ReCaptchaV2EnterpriseTask', t.type === 'ReCaptchaV2EnterpriseTask');
  check('commercial gate derives proxy (socks5h→socks5)', t.proxy === 'socks5:10.0.0.1:9000:cust:pw');
  check('commercial gate forwards s', t.enterprisePayload && t.enterprisePayload.s === 'SDATA');
}

delete process.env.PROXY_SOLVER;
delete process.env.PROXY_GATE;
delete process.env.PROXY_USER;
delete process.env.PROXY_PASS;

if (failures > 0) {
  console.error('\ncapsolver-task-check: ' + failures + ' failure(s)');
  process.exit(1);
}
console.log('\ncapsolver-task-check: all cases passed');
