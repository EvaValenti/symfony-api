<?php

namespace App\Entity;

use App\Enum\Mime;
use App\Repository\FileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FileRepository::class)]
class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $originalName = null;

    #[ORM\Column(length: 255)]
    private ?string $storedName = null;

    #[ORM\Column]
    private ?int $size = null;

    #[ORM\Column]
    private ?Mime $mimeType = null;

    #[ORM\ManyToOne]
    private ?User $uploadedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getStoredName(): ?string
    {
        return $this->storedName;
    }

    public function setStoredName(string $storedName): static
    {
        $this->storedName = $storedName;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getMimeType(): ?Mime
    {
        return $this->mimeType;
    }

    public function setMimeType(Mime $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?User $uploadedBy): static
    {
        $this->uploadedBy = $uploadedBy;

        return $this;
    }
}
