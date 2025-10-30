<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'review', uniqueConstraints: [
    new UniqueConstraint(name: 'user_book_unique', columns: ['user_id', 'book_id'])
])]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["review_detail"])]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(["review_detail"])]
    private ?string $content = null;

    #[ORM\Column]
    #[Groups(["review_detail"])]
    private ?int $rating = null;

    #[ORM\ManyToOne(targetEntity: Book::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Book $book = null;

    #[ORM\OneToMany(mappedBy: 'review', targetEntity: ReviewLike::class, cascade: ['remove'])]
    private Collection $likes;

    #[ORM\Column]
    #[Groups(["review_detail"])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[Groups(["review_detail"])]
    private ?User $user = null;

    public function __construct()
    {
        $this->likes = new ArrayCollection();
        $this->likedBy = new ArrayCollection();
        // If you already have a constructor, just add the $likes initialization to it
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getBookId(): ?string
    {
        return $this->bookId;
    }

    public function setBookId(string $bookId): static
    {
        $this->bookId = $bookId;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

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

    // Getters and Setters
    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(?Book $book): static
    {
        $this->book = $book;
        return $this;
    }
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    #[Groups(['review_detail'])]
    public function getBookSummary(): ?array
    {
        if (!$this->book) {
            return null;
        }

        return [
            'id' => $this->book->getId(),
            'googleBookId' => $this->book->getGoogleBookId(),
            'title' => $this->book->getTitle(),
            'thumbnail' => $this->book->getThumbnail(),
        ];
    }


    #[Groups(['review_detail'])]
    public function getLikesCount(): int
    {
        return $this->likes->count();
    }

    #[Groups(['review_detail'])]
    public function isLikedByUser(?User $user): bool
    {
        if (!$user) return false;

        foreach ($this->likes as $like) {
            if ($like->getUser()->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }

}
