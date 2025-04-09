<?php
/**
 * BookFactory for creating Book entity instances.
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

use App\Entity\Book;
use Monolog\DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * Factory for creating Book entities.
 *
 * @category Factory
 * @package  App\Factory
 * @author   John Doe <john.doe@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 * @extends  PersistentProxyObjectFactory<Book>
 */
final class BookFactory extends PersistentProxyObjectFactory
{
    /**
     * Constructor for BookFactory.
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
        return Book::class;
    }

    /**
     * Defines the default values for Book entities.
     *
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     *
     * @return array|callable The default values for Book entities
     */
    protected function defaults(): array|callable
    {
        return [
            'author' => self::faker()->name(),
            'description' => self::faker()->text(),
            'isbn' => self::faker()->isbn13(),
            'publicationDate' => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTime()
            ),
            'title' => self::faker()->sentence(4),
        ];
    }

    /**
     * Initializes the Book factory with additional setup after instantiation.
     *
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     *
     * @return static The initialized factory
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Book $book): void {})
        ;
    }
}
