<?php
/**
 * AppFixtures for loading test data into the database.
 *
 * PHP version 8.1
 *
 * @category DataFixture
 * @package  App\DataFixtures
 * @author   John Doe <john.doe@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @version  GIT: <git_id>
 * @link     https://api-platform.com
 */

namespace App\DataFixtures;

use App\Story\DefaultBooksStory;
use App\Story\DefaultReviewsStory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Main fixture class that loads all test data for the application.
 *
 * @category DataFixture
 * @package  App\DataFixtures
 * @author   John Doe <john.doe@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */
class AppFixtures extends Fixture
{
    /**
     * Load data fixtures into the database.
     *
     * @param ObjectManager $manager The Doctrine entity manager
     *
     * @return void
     */
    public function load(ObjectManager $manager): void
    {
        // $product = new Product();
        // $manager->persist($product);

        DefaultBooksStory::load();
        DefaultReviewsStory::load();

        $manager->flush();
    }
}
