<?php

namespace PiedWeb\Google;

final class Sleeper
{
    /** @var int Time we need to wait between two request * */
    private int $sleep = 0;

    /**
     * Chainable `$waitBetweenRequests` setter.
     *
     * @return self
     */
    public function __construct(int $averageSleepTimeInseconds)
    {
        $this->sleep = $averageSleepTimeInseconds * 1_000_000;
    }

    /**
     * Return the time the script need to sleep.
     *
     * @return int Microseconds
     */
    private function getSleep(): int
    {
        $halfSleep = $this->sleep / 2;
        $sleepMin = (int) floor($this->sleep - $halfSleep);
        $sleepMax = (int) ceil($this->sleep + $halfSleep);

        return random_int($sleepMin, $sleepMax);
    }

    /**
     * Exec sleep.
     */
    public function execSleep(): void
    {
        if (0 !== $this->sleep) {
            $sleep = $this->getSleep();
            usleep($sleep);
            Logger::log('sleep '.($sleep / 1_000_000).'s');
        }
    }

    /**
     * Exec a half sleep.
     */
    public function execPartialSleep(float $howMuch = 0.5): void
    {
        if (0 !== $this->sleep) {
            $sleep = (int) round($this->getSleep() * $howMuch);
            usleep($sleep);
            Logger::log('sleep '.($sleep / 1_000_000).'s');
        }
    }
}
