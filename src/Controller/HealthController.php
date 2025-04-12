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

use Psr\Log\LoggerInterface;
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
     * Logger instance.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger The logger service
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Simple health check endpoint that returns a 200 status code.
     *
     * @return JsonResponse The health status response
     */
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $timestamp = new \DateTime();
        
        // Test log entry to verify logging is working
        $this->logger->info('Health check endpoint accessed', [
            'timestamp' => $timestamp->format('Y-m-d H:i:s'),
            'environment' => $this->getParameter('kernel.environment')
        ]);
        
        return new JsonResponse(
            [
                'status' => 'ok',
                'timestamp' => $timestamp,
                'loggingTest' => 'Log entry created - check /var/logs directory'
            ]
        );
    }
}
