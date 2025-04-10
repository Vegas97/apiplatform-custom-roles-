<?php
/**
 * Book entity for the API Platform application.
 *
 * PHP version 8.1
 *
 * @category Entity
 * @package  App\Entity
 * @author   John Doe <john.doe@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @version  GIT: <git_id>
 * @link     https://api-platform.com
 */
// api/src/Entity/Book.php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Link;
use App\Attribute\AllowedRoles;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Book entity class.
 *
 * @category Entity
 * @package  App\Entity
 * @author   John Doe <john.doe@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @version  GIT: <git_id>
 * @link     https://api-platform.com
 */
#[ORM\Entity]
#[ApiResource]
class Book // phpcs:disable PEAR.NamingConventions.ValidVariableName.PrivateNoUnderscore
{
    /**
     * The ID of this book.
     */
    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    #[ApiProperty(identifier: true)]
    #[AllowedRoles(
        [
            'admin' => ['ROLE_BOOK_ACCESS'],
            'workspace' => ['ROLE_BOOK_ACCESS'],
            'distributor' => ['ROLE_BOOK_ACCESS']
        ]
    )]
    private ?int $id = null;

    /**
     * The ISBN of this book (or null if doesn't have one).
     */
    #[ORM\Column(nullable: true)]
    #[Assert\Isbn]
    #[AllowedRoles(
        [
            'admin' => ['ROLE_BOOK_ACCESS'],
            'workspace' => ['ROLE_BOOK_ACCESS'],
            'distributor' => ['ROLE_BOOK_ACCESS']
        ]
    )]
    public ?string $isbn = null;

    /**
     * The title of this book.
     */
    #[ORM\Column]
    #[Assert\NotBlank]
    #[AllowedRoles(
        [
            'admin' => ['ROLE_BOOK_ACCESS'],
            'workspace' => ['ROLE_BOOK_ACCESS'],
            'distributor' => ['ROLE_BOOK_ACCESS']
        ]
    )]
    public string $title = '';

    /**
     * The description of this book.
     */
    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[AllowedRoles(
        [
            'admin' => ['ROLE_BOOK_ACCESS'],
            'workspace' => ['ROLE_BOOK_ACCESS'],
            'distributor' => ['ROLE_BOOK_ACCESS']
        ]
    )]
    public string $description = '';

    /**
     * The author of this book.
     */
    #[ORM\Column]
    #[Assert\NotBlank]
    #[AllowedRoles(
        [
            'admin' => ['ROLE_BOOK_ACCESS'],
            'workspace' => ['ROLE_BOOK_ACCESS']
            // No distributor access to this field
        ]
    )]
    public string $author = '';

    /**
     * The publication date of this book.
     */
    #[ORM\Column]
    #[Assert\NotNull]
    #[AllowedRoles(
        [
            'admin' => ['ROLE_BOOK_ACCESS'],
            'workspace' => ['ROLE_BOOK_ACCESS']
            // No distributor access to this field
        ]
    )]
    public ?\DateTimeImmutable $publicationDate = null;

    /**
     * Available reviews for this book.
     *
     * @var Review[] Collection of review objects
     */
    #[ORM\OneToMany(
        targetEntity: Review::class,
        mappedBy: 'book',
        cascade: ['persist', 'remove']
    )]
    #[AllowedRoles(
        [
            'admin' => ['ROLE_BOOK_ACCESS']
            // No workspace or distributor access to this field
        ]
    )]
    public iterable $reviews;

    /**
     * Constructor.
     *
     * Initializes the reviews collection.
     */
    public function __construct()
    {
        $this->reviews = new ArrayCollection();
    }

    /**
     * Get the book ID.
     *
     * @return ?int The book ID or null if not set
     */
    public function getId(): ?int
    {
        return $this->id;
    }
}
