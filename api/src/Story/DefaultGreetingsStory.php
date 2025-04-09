<?php

namespace App\Story;

use App\Factory\GreetingFactory;
use Zenstruck\Foundry\Story;

final class DefaultGreetingsStory extends Story
{
    public function build(): void
    {
        // TODO build your story here (https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#stories)
        GreetingFactory::createMany(5);
    }
}
