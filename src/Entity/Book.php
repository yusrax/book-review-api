<?php

namespace App\Entity;

use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: BookRepository::class)]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['book_list', 'book_detail', 'review_detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['book_list', 'book_detail', 'review_detail'])]
    private ?string $googleBookId = null;

    #[ORM\Column(length: 255)]
    #[Groups(['book_list', 'book_detail', 'review_detail'])]
    private string $title;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['book_list', 'book_detail'])]
    private array $authors = [];

    #[ORM\Column(length: 1000, nullable: true)]
    #[Groups(['book_list', 'book_detail', 'review_detail'])]
    private ?string $thumbnail = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['book_list', 'book_detail'])]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['book_list', 'book_detail'])]
    private ?int $pageCount = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['book_list', 'book_detail'])]
    private array $categories = [];

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['book_list', 'book_detail'])]
    private ?float $averageRating = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['book_list', 'book_detail'])]
    private ?int $reviewCount = null;


    #[ORM\OneToMany(mappedBy: 'book', targetEntity: Review::class, orphanRemoval: true)]
    #[Groups(['book_detail'])]
    private Collection $reviews;

    #[ORM\ManyToMany(mappedBy: 'readingList', targetEntity: User::class)]
    private Collection $savedByUsers;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGoogleBookId(): ?string
    {
        return $this->googleBookId;
    }

    public function setGoogleBookId(string $googleBookId): self
    {
        $this->googleBookId = $googleBookId;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getAuthors(): array
    {
        return $this->authors;
    }

    public function setAuthors(?array $authors): self
    {
        $this->authors = $authors ?? [];
        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): self
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPageCount(): ?int
    {
        return $this->pageCount;
    }

    public function setPageCount(?int $pageCount): self
    {
        $this->pageCount = $pageCount;
        return $this;
    }

    public function getCategories(): array
    {
        return $this->categories;
    }

    public function setCategories(?array $categories): self
    {
        $this->categories = $categories ?? [];
        return $this;
    }

    public function getAverageRating(): ?float
    {
        return $this->averageRating;
    }

    #[Groups(['book_list', 'book_detail'])]
    public function getReviewCount(): ?int
    {
        return $this->reviewCount;
    }


    public function setAverageRating(?float $averageRating): self
    {
        $this->averageRating = $averageRating;
        return $this;
    }

    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function calculateAverageRating(): void
    {
        $reviews = $this->getReviews();
        $count = count($reviews);

        $this->reviewCount = $count;

        if ($count === 0) {
            $this->averageRating = null;
            return;
        }

        $total = 0;
        foreach ($reviews as $review) {
            $total += $review->getRating();
        }

        $this->averageRating = round($total / $count, 2);
    }

    #[Groups(['book_list', 'book_detail'])]
    public function getReadingListCount(): int
    {
        return $this->savedByUsers->count();
    }

}
