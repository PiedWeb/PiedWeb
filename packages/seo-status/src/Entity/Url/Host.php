<?php

namespace PiedWeb\SeoStatus\Entity\Url;

use Doctrine\ORM\Mapping as ORM;
use PiedWeb\SeoStatus\Repository\Url\HostRepository;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity(repositoryClass: HostRepository::class)]
class Host implements \Stringable
{
    /** @noRector */
    #[ORM\Id, ORM\Column(type: 'bigint', options: ['unsigned' => true]), ORM\GeneratedValue]
    private int $id; // @phpstan-ignore-line

    #[ORM\Column(length: 253)]
    private string $host;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private Domain $domain;

    public function __toString(): string
    {
        return $this->host;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): self
    {
        $this->host = strtolower($host);

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDomain(): Domain
    {
        return $this->domain;
    }

    public function setDomain(Domain $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public static function normalizeHost(mixed $host): string
    {
        $host = strtolower(\strval($host));
        $hostLenght = \strlen($host);
        if ($hostLenght < 3 || $hostLenght > 253) {
            return '';
        }

        $host = str_starts_with($host, 'http://') ? substr($host, 7)
                : (str_starts_with($host, 'https://') ? substr($host, 8) : $host);

        $host = trim($host, ' /.');

        $slashPosition = strpos($host, '/');
        $host = $slashPosition >= 1 ? substr($host, 0, $slashPosition) : $host;

        $host = false === str_contains($host, '.') || false === filter_var('https://'.$host.'/', \FILTER_VALIDATE_URL) ? '' : $host;

        return $host;
    }
}
