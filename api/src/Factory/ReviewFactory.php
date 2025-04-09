<?php
/**
 * ReviewFactory for creating Review entity instances.
 *
 * PHP version 8.1
 *
 * @category Factory
 * @package  App\Factory
 * @author   John Doe <john.doe@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @version  GIT: <git_id>
 * @link     https://api-platform.com
 */

namespace App\Factory;

use App\Entity\Review;
use Monolog\DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use function Zenstruck\Foundry\lazy;

/**
 * Factory for creating Review entities.
 *
 * @category Factory
 * @package  App\Factory
 * @author   John Doe <john.doe@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 * @extends  PersistentProxyObjectFactory<Review>
 */
final class ReviewFactory extends PersistentProxyObjectFactory
{
    /**
     * Constructor for ReviewFactory.
     *
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
    }

    /**
     * Returns the class this factory creates.
     *
     * @return string The fully qualified class name
     */
    public static function class(): string
    {
        return Review::class;
    }

    /**
     * Defines the default values for Review entities.
     *
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     *
     * @return array|callable The default values for Review entities
     */
    protected function defaults(): array|callable
    {
        return [
            'author' => self::faker()->name(),
            'body' => self::faker()->text(),
            'book' => lazy(fn() => BookFactory::randomOrCreate()),
            'publicationDate' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'rating' => self::faker()->numberBetween(0, 5),
        ];
    }

    /**
     * Initializes the Review factory with additional setup after instantiation.
     *
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     *
     * @return static The initialized factory
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Review $review): void {})
        ;
    }
}
