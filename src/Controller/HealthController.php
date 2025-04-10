<?php

/**
 * Health controller for API health checks.
 *
 * @category Controller
 * @package  App\Controller
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 * @version  PHP 8.4
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for health check endpoints.
 *
 * @category Controller
 * @package  App\Controller
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
class HealthController extends AbstractController
{
    /**
     * Simple health check endpoint that returns a 200 status code.
     *
     * @return JsonResponse The health status response
     */
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $timestamp = new \DateTime();
        
        return new JsonResponse(
            [
                'status' => 'ok',
                'timestamp' => $timestamp,
            ]
        );
    }
}
