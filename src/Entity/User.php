<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity()]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // === ID ===
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(["user_list", "user_detail", "review_detail", "user_public_profile"])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(["user_list", "user_detail", "review_detail", "user_public_profile"])]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(["user_list", "user_detail", "review_detail", "user_public_profile"])]
    private ?string $profilePicture = null;

    // === EMAIL ===
    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Groups(["user_list", "user_detail", "review_detail"])]
    private string $email;

    // === ROLES ===
    #[ORM\Column(type: 'json')]
    #[Groups(["user_list", "user_detail"])]
    private array $roles = [];

    // === PASSWORD ===
    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'boolean')]
    #[Groups(["user_list", "user_detail"])]
    private bool $isBanned = false;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Review::class, orphanRemoval: true, cascade: ['remove'])]
    #[Groups(['user_public_profile'])] // Expose reviews in public profile
    private Collection $reviews;

    #[ORM\ManyToMany(targetEntity: Book::class)]
    #[ORM\JoinTable(name: 'user_reading_list')]
    #[Groups(['user_detail', 'user_public_profile'])] // Expose reading list in public profile
    private Collection $readingList;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'followers')]
    #[ORM\JoinTable(name: 'user_following')]
    #[ORM\JoinColumn(name: 'follower_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'following_id', referencedColumnName: 'id')]
    private Collection $following;

    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'following')]
    private Collection $followers;

    // === CONSTRUCTOR ===
    public function __construct()
    {
        $this->roles = ['ROLE_USER'];
        $this->reviews = new ArrayCollection();
        $this->readingList = new ArrayCollection();
        $this->following = new ArrayCollection();
        $this->followers = new ArrayCollection();
    }

    // === GETTERS & SETTERS ===
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): self
    {
        $this->profilePicture = $profilePicture;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;

        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
        // You can clear temporary sensitive data here if needed.
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setUser($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getUser() === $this) {
                $review->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Book>
     */
    public function getReadingList(): Collection
    {
        return $this->readingList;
    }

    public function addToReadingList(Book $book): static
    {
        if (!$this->readingList->contains($book)) {
            $this->readingList->add($book);
        }

        return $this;
    }

    public function removeFromReadingList(Book $book): static
    {
        $this->readingList->removeElement($book);
        return $this;
    }

    public function hasInReadingList(Book $book): bool
    {
        return $this->readingList->contains($book);
    }

    public function isBanned(): bool
    {
        return $this->isBanned;
    }

    public function setIsBanned(bool $isBanned): self
    {
        $this->isBanned = $isBanned;
        return $this;
    }
    
    /**
     * Get a summary of the user's public profile
     * This method is used for the public profile serialization
     */
    #[Groups(['user_public_profile'])]
    public function getPublicProfile(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'profilePicture' => $this->profilePicture,
            'readingListCount' => $this->readingList->count(),
            'reviewsCount' => $this->reviews->count()
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function getFollowing(): Collection
    {
        return $this->following;
    }

    public function addFollowing(User $user): self
    {
        if (!$this->following->contains($user)) {
            $this->following->add($user);
        }

        return $this;
    }

    public function removeFollowing(User $user): self
    {
        $this->following->removeElement($user);

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getFollowers(): Collection
    {
        return $this->followers;
    }

    public function addFollower(User $user): self
    {
        if (!$this->followers->contains($user)) {
            $this->followers->add($user);
        }

        return $this;
    }

    public function removeFollower(User $user): self
    {
        $this->followers->removeElement($user);

        return $this;
    }
}
