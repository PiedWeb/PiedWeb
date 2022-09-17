<?php

namespace PiedWeb\SeoStatus\Entity\Url;

use Doctrine\ORM\Mapping as ORM;
use LogicException;
use PiedWeb\SeoStatus\Repository\Url\UrlRepository;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity(repositoryClass: UrlRepository::class)]
class Url implements \Stringable
{
    /** @noRector */
    #[ORM\Id, ORM\Column(type: 'bigint', options: ['unsigned' => true]), ORM\GeneratedValue]
    private int $id; // @phpstan-ignore-line

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private Host $host;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private Uri $uri;

    #[ORM\Column]
    public int $schemeCode = 0;

    /** @var array<int, string> */
    public const SCHEME = [
        0 => '',
        1 => 'http',
        2 => 'https',
        3 => 'ftp',
    ];

    public function __toString(): string
    {
        return $this->getUrl();
    }

    #[Ignore]
    public function getUrl(): string
    {
        return self::SCHEME[$this->schemeCode].'://'.$this->host.'/'.$this->uri;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getHost(): Host
    {
        return $this->host;
    }

    public function setHost(Host $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function getUri(): Uri
    {
        return $this->uri;
    }

    public function setUri(Uri $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function getScheme(): string
    {
        return self::SCHEME[$this->schemeCode];
    }

    public function setSchemeCode(int $schemeCode): self
    {
        $this->schemeCode = $schemeCode;

        return $this;
    }

    public function getSchemeCode(): int
    {
        return $this->schemeCode;
    }

    public function setScheme(string $scheme): self
    {
        $this->schemeCode = static::retrieveShemeCode($scheme);

        return $this;
    }

    public function setSchemeFrom(string $url): self
    {
        $scheme = parse_url($url, \PHP_URL_SCHEME);
        if (! \is_string($scheme)) {
            throw new LogicException();
        }

        return $this->setScheme($scheme);
    }

    public static function retrieveShemeCode(string $scheme): int
    {
        $schemes = array_flip(self::SCHEME);

        return $schemes[strtolower($scheme)] ?? 0;
    }
}
