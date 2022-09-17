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
}
