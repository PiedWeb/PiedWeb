<?php

namespace PiedWeb\SeoStatus\Entity\Url;

use Doctrine\ORM\Mapping as ORM;
use PiedWeb\SeoStatus\Repository\Url\DomainRepository;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity(repositoryClass: DomainRepository::class)]
class Domain implements \Stringable
{
    /** @noRector */
    #[ORM\Id, ORM\Column(type: 'bigint', options: ['unsigned' => true]), ORM\GeneratedValue]
    private int $id; // @phpstan-ignore-line

    #[ORM\Column(length: 253)]
    private string $domain;

    public function __toString(): string
    {
        return $this->domain;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = strtolower($domain);

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }
}
