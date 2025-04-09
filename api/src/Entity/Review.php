<?php
// api/src/Entity/Review.php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

/** A review of a book. */
#[ORM\Entity]
#[ApiResource]
class Review
{
    /** The ID of this review. */
    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    private ?int $id = null;

    /** The rating of this review (between 0 and 5). */
    #[ORM\Column(type: 'smallint')]
    public int $rating = 0;

    /** The body of the review. */
    #[ORM\Column(type: 'text')]
    public string $body = '';

    /** The author of the review. */
    #[ORM\Column]
    public string $author = '';

    /** The date of publication of this review.*/
    #[ORM\Column]
    public ?\DateTimeImmutable $publicationDate = null;

    /** The book this review is about. */
    #[ORM\ManyToOne(inversedBy: 'reviews')]
     public ?Book $book = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}