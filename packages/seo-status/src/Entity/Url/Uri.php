<?php

namespace PiedWeb\SeoStatus\Entity\Url;

use Doctrine\ORM\Mapping as ORM;
use PiedWeb\SeoStatus\Repository\Url\UriRepository;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity(repositoryClass: UriRepository::class)]
class Uri implements \Stringable
{
    /** @noRector */
    #[ORM\Id, ORM\Column(type: 'bigint', options: ['unsigned' => true]), ORM\GeneratedValue]
    private int $id; // @phpstan-ignore-line

    #[ORM\Column(length: 255)]
    private string $uri = '';

    public function __toString(): string
    {
        return $this->uri;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function setUri(string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }
}
