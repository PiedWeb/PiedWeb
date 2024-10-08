<?php

namespace PiedWeb\Curl;

trait UserAgentTrait
{
    public string $desktopUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36';

    public string $mobileUserAgent = 'Mozilla/5.0 (Linux; Android 10; M10 4G PRO X Build/QP1A.190711.020) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36	';

    public string $lessJsUserAgent = 'NokiaN70-1/5.0609.2.0.1 Series60/2.8 Profile/MIDP-2.0 Configuration/CLDC-1.1 UP.Link/6.3.1.13.0';

    abstract public function setUserAgent(string $ua);

    /**
     * An self::setUserAgent()'s alias to add an user-agent wich correspond to a Desktop PC.
     *
     * @return self
     */
    public function setDesktopUserAgent()
    {
        $this->setUserAgent($this->desktopUserAgent);

        return $this;
    }

    /**
     * An self::setUserAgent()'s alias to add an user-agent wich correspond to a mobile.
     *
     * @return self
     */
    public function setMobileUserAgent()
    {
        $this->setUserAgent($this->mobileUserAgent);

        return $this;
    }

    /**
     * An self::setUserAgent()'s alias to add an user-agent wich correspond to a webrowser without javascript.
     *
     * @return self
     */
    public function setLessJsUserAgent()
    {
        $this->setUserAgent($this->lessJsUserAgent);

        return $this;
    }
}
