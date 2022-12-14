<?php

namespace PiedWeb\Curl;

trait UserAgentTrait
{
    public string $desktopUserAgent = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:64.0) Gecko/20100101 Firefox/64.0';

    public string $mobileUserAgent = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.5195.79 Mobile Safari/537.36';

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
