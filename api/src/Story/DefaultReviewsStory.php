<?php
/**
 * DefaultReviewsStory for creating review data fixtures.
 *
 * PHP version 8.1
 *
 * @category Story
 * @package  App\Story
 * @author   John Doe <john.doe@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @version  GIT: <git_id>
 * @link     https://api-platform.com
 */

namespace App\Story;

use App\Factory\ReviewFactory;
use Zenstruck\Foundry\Story;

/**
 * Story that creates a default set of review entities for testing and development.
 *
 * @category Story
 * @package  App\Story
 * @author   John Doe <john.doe@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */
final class DefaultReviewsStory extends Story
{
    /**
     * Builds the story by creating review entities.
     *
     * @return void
     */
    public function build(): void
    {
        // TODO build your story here (https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#stories)
        ReviewFactory::createMany(200);
    }
}
