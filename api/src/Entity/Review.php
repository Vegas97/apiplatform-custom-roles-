<?php
/**
 * Review entity for the API Platform application.
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
// api/src/Entity/Review.php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Link;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Review entity class.
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
class Review // phpcs:disable PEAR.NamingConventions.ValidVariableName.PrivateNoUnderscore
{
    /**
     * The ID of this review.
     */
    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    #[ApiProperty(identifier: true)]
    private ?int $id = null;

    /**
     * The rating of this review (between 0 and 5).
     */
    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 0, max: 5)]
    public int $rating = 0;

    /**
     * The body of the review.
     */
    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    public string $body = '';

    /**
     * The author of the review.
     */
    #[ORM\Column]
    #[Assert\NotBlank]
    public string $author = '';

    /**
     * The date of publication of this review.
     */
    #[ORM\Column]
    #[Assert\NotNull]
    public ?\DateTimeImmutable $publicationDate = null;

    /**
     * The book this review is about.
     */
    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[Assert\NotNull]
    public ?Book $book = null;

    /**
     * Get the review ID.
     *
     * @return ?int The review ID or null if not set
     */
    public function getId(): ?int
    {
        return $this->id;
    }
}
