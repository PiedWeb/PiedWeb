<?php

namespace PiedWeb\SeoStatus\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use PiedWeb\SeoStatus\Repository\ProxyRepository;

#[ORM\Entity(repositoryClass: ProxyRepository::class)]
class Proxy
{
    #[ORM\Id, ORM\Column(length: 255)]
    private string $proxy;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $lastUsedAt;

    #[ORM\Column]
    private string $countryCode = 'fr';

    #[ORM\Column]
    private bool $googleBlacklist = false;

    public function __construct()
    {
        $datetime = DateTime::createFromFormat('j-M-Y', '01-01-2001');
        $this->lastUsedAt = $datetime instanceof Datetime ? $datetime : throw new LogicException();
    }

    public function setProxy(string $proxy): void
    {
        $this->proxy = $proxy;
    }

    public function getProxy(): string
    {
        return $this->proxy;
    }

    public function isGoogleBlacklist(): bool
    {
        return $this->googleBlacklist;
    }

    public function setGoogleBlacklist(bool $googleBlacklist): self
    {
        $this->googleBlacklist = $googleBlacklist;

        return $this;
    }

    public function getLastUsedAt(): DateTimeInterface
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedNow(): self
    {
        $this->lastUsedAt = new Datetime('now');

        return $this;
    }

    public function setLastUsedAt(DateTimeInterface $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = $countryCode;

        return $this;
    }
}
