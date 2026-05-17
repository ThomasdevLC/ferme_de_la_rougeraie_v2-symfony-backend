<?php

namespace App\Entity;

use App\Enum\MessageType;
use App\Repository\Admin\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: MessageType::class)]
    private ?MessageType $type = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?MessageType
    {
        return $this->type;
    }

    public function setType(MessageType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = self::normalizeContent($content);

        return $this;
    }

    public static function normalizeContent(string $content): string
    {
        $content = str_replace('&nbsp;', ' ', $content);
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content) ?? $content;
        $content = preg_replace('/<\/p>\s*<p>/i', "\n", $content) ?? $content;
        $content = preg_replace('/<\/div>\s*<div>/i', "\n", $content) ?? $content;
        $content = preg_replace('/<\/?(div|p)>/i', '', $content) ?? $content;
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace("/[ \t]+\n/", "\n", $content) ?? $content;
        $content = preg_replace("/\n{3,}/", "\n\n", $content) ?? $content;

        return trim($content);
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
    public function getIsActiveLabel(): string
    {
        return $this->isActive ? '✅ Oui' : '❌ Non';
    }
}
