<?php

declare(strict_types=1);

namespace Phobetor\Billomat\Client;

use Phobetor\Billomat\Exception\BadRequestException;
use Phobetor\Billomat\Exception\ExceptionInterface;
use Phobetor\Billomat\Exception\ExceptionListException;
use Phobetor\Billomat\Exception\NotFoundException;
use Phobetor\Billomat\Exception\TooManyRequestsException;
use Phobetor\Billomat\Exception\UnauthorizedException;
use Phobetor\Billomat\Exception\UnknownErrorException;
use Psr\Http\Message\ResponseInterface;

/**
 * Converts Billomat error responses to a specific Exception, depending
 * on the status code and with a message corresponding to the Billomat error.
 * This will always be wrapped in a CommandException by Guzzle, so access
 * it via $exception->getPrevious() in your try-catch.
 */
abstract class ErrorHandlerMiddleware
{
    const STATUS_OK                 = 200;
    const STATUS_CREATED            = 201;
    const STATUS_BAD_REQUEST        = 400;
    const STATUS_UNAUTHORIZED       = 401;
    const STATUS_NOT_FOUND          = 404;
    const STATUS_TOO_MANY_REQUESTS  = 429;
    const SERVER_DOWN               = 503;
    const SERVER_DOWN_2             = 504;

    /**
     * @throws \Exception
     */
    public static function handleResponse(?ResponseInterface $response): ?ResponseInterface
    {
        if (!$response) {
            return $response;
        }

        $statusCode = $response->getStatusCode();

        switch ($statusCode) {
            case self::STATUS_OK:
            case self::STATUS_CREATED:
                return $response;
        }

        $result    = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        $errorName = isset($result['errors']['error']) ? $result['errors']['error'] : 'Unknown error';
        $exception = null;

        if (is_array($errorName)) {
            $exception = new ExceptionListException('', $statusCode);
            foreach ($errorName as $singleErrorName) {
                $exception->addException(
                    self::createExceptionFromStatusCode($singleErrorName, $response, $statusCode)
                );
            }
        }
        else {
            $exception = self::createExceptionFromStatusCode($errorName, $response, $statusCode);
        }

        throw $exception;
    }

    protected static function createExceptionFromStatusCode(
        string $errorName,
        ResponseInterface $response,
        int $statusCode
    ): ExceptionInterface
    {
        switch ($statusCode) {
            case self::STATUS_NOT_FOUND:
                return new NotFoundException($errorName, $statusCode);

            case self::STATUS_BAD_REQUEST:
                return new BadRequestException($errorName, $statusCode);

            case self::STATUS_UNAUTHORIZED:
                return new UnauthorizedException($errorName, $statusCode);

            case self::STATUS_TOO_MANY_REQUESTS:
            case self::SERVER_DOWN:
            case self::SERVER_DOWN_2:
                $exception = new TooManyRequestsException($errorName, $statusCode);
                if ($response->hasHeader('X-Rate-Limit-Remaining')) {
                    $exception->setRateLimitRemaining((int)$response->getHeader('X-Rate-Limit-Remaining')[0]);
                }
                if ($response->hasHeader('X-Rate-Limit-Reset')) {
                    $exception->setRateLimitReset((int)$response->getHeader('X-Rate-Limit-Reset')[0]);
                }
                return $exception;

            default:
                return new UnknownErrorException($errorName, $statusCode);
        }
    }
}
