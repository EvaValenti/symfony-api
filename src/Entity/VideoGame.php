<?php

namespace App\Entity;

use App\Repository\VideoGameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VideoGameRepository::class)]
class VideoGame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['getVideoGames', 'getVideoGame'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['getVideoGames', 'getVideoGame'])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'The title must be at least {{ limit }} characters long',
        maxMessage: 'The title cannot be longer than {{ limit }} characters',
    )]
    private ?string $title = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['getVideoGames', 'getVideoGame'])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    private ?\DateTime $releaseDate = null;

    #[ORM\Column(length: 255)]
    #[Groups(['getVideoGames', 'getVideoGame'])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Length(
        min: 10,
        max: 255,
        minMessage: 'The description must be at least {{ limit }} characters long',
        maxMessage: 'The description cannot be longer than {{ limit }} characters',
    )]
    private ?string $description = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'videoGames')]
    #[Groups(['getVideoGames'])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    private Collection $category_id;

    #[ORM\ManyToOne(inversedBy: 'videoGames')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['getVideoGames'])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    private ?Editor $editor_id = null;

    #[ORM\ManyToOne]
    private ?File $coverImage = null;

    public function __construct()
    {
        $this->category_id = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getReleaseDate(): ?\DateTime
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(\DateTime $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategoryId(): Collection
    {
        return $this->category_id;
    }

    public function addCategoryId(Category $categoryId): static
    {
        if (!$this->category_id->contains($categoryId)) {
            $this->category_id->add($categoryId);
        }

        return $this;
    }

    public function removeCategoryId(Category $categoryId): static
    {
        $this->category_id->removeElement($categoryId);

        return $this;
    }

    public function getEditorId(): ?Editor
    {
        return $this->editor_id;
    }

    public function setEditorId(?Editor $editor_id): static
    {
        $this->editor_id = $editor_id;

        return $this;
    }

    public function getCoverImage(): ?File
    {
        return $this->coverImage;
    }

    public function setCoverImage(?File $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
    }
}
