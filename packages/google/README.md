# Google (unofficial) API via PHP

[![Latest Version](https://img.shields.io/github/tag/PiedWeb/PiedWeb.svg?style=flat&label=release)](https://github.com/PiedWeb/PiedWeb/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/PiedWeb/PiedWeb/run-tests.yml?branch=main)](https://github.com/PiedWeb/PiedWeb/actions)
[![Quality Score](https://img.shields.io/scrutinizer/g/PiedWeb/PiedWeb.svg?style=flat)](https://scrutinizer-ci.com/g/PiedWeb/PiedWeb)
[![Code Coverage](https://codecov.io/gh/PiedWeb/PiedWeb/branch/main/graph/badge.svg)](https://codecov.io/gh/PiedWeb/PiedWeb/branch/main)
[![Type Coverage](https://shepherd.dev/github/PiedWeb/PiedWeb/coverage.svg)](https://shepherd.dev/github/PiedWeb/PiedWeb)
[![Total Downloads](https://img.shields.io/packagist/dt/piedweb/google.svg?style=flat)](https://packagist.org/packages/piedweb/google)

Via Curl or Puppeteer. This lib offers for now :

- SERP extraction
- Suggests
- _Trends_ dropped

## Requirements

- node (tested with v20)
- [puppeteer](package.json) (tested with v23) `npm install puppeteer puppeteer-extra puppeteer-extra-plugin-stealth puppeteer-extra-plugin-recaptcha`
- php ^8.3
- `composer require piedweb/google`

## Google

Still no docs, see [tests/\*](tests/) :

## Fix TroubleShoot

```
Failed to launch the browser process!
  [57007:57007:1010/104537.301500:FATAL:zygote_host_impl_linux.cc(128)] No usable sandbox! If you are running o n Ubuntu 23.10+ or another Linux distro that has disabled unprivileged user namespaces with AppArmor, see https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md. Otherwise seehttps://chromium.googlesource.com/chromium/src/+/main/docs/linux/suid_sandbox_development.md for more information on developing with the (older) SUID sandbox. If you want to live dangerously and need an immediate workaround, you can try using --no-sandbox.

  TROUBLESHOOTING: https://pptr.dev/troubleshooting
```

Since Ubuntu 23 (same with Ubuntu 24), it's not possible to directly use the chrome version downloaded by _puppeteer_ on package installation.

Workaround are details here :
https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md

It's also possible to use an ever installed Chrome on your OS by defining _env_ variable **CHROME_BIN**.

### Contributors

- [Pied Web](https://piedweb.com)

## See Also

- [PyTrends](https://github.com/GeneralMills/pytrends)
- [JsTrends](https://github.com/pat310/google-trends-ap)

<p align="center"><a href="https://dev.piedweb.com" rel="dofollow">
<img src="https://raw.githubusercontent.com/Pushword/Pushword/f5021f4c5d5d3ab3f2858ec2e4bdd70818806c6a/packages/admin/src/Resources/assets/logo.svg" width="200" height="200" alt="PHP Packages Open Source" />
</a></p>
