<?php
/**
 * Connection Result
 *
 * Represents the result of a provider connection test.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ConnectionResult
 *
 * Value object for connection test results.
 */
class ConnectionResult {

    /**
     * Whether the connection was successful.
     *
     * @var bool
     */
    public bool $success;

    /**
     * A message describing the result.
     *
     * @var string
     */
    public string $message;

    /**
     * HTTP status code (if applicable).
     *
     * @var int|null
     */
    public ?int $statusCode;

    /**
     * Response time in milliseconds.
     *
     * @var float|null
     */
    public ?float $responseTime;

    /**
     * Additional details (e.g., user info, rate limit status).
     *
     * @var array
     */
    public array $details;

    /**
     * Constructor.
     *
     * @param bool       $success      Whether the connection succeeded.
     * @param string     $message      Result message.
     * @param int|null   $status_code  HTTP status code.
     * @param float|null $response_time Response time in ms.
     * @param array      $details      Additional details.
     */
    public function __construct(
        bool $success,
        string $message,
        ?int $status_code = null,
        ?float $response_time = null,
        array $details = []
    ) {
        $this->success      = $success;
        $this->message      = $message;
        $this->statusCode   = $status_code;
        $this->responseTime = $response_time;
        $this->details      = $details;
    }

    /**
     * Create a successful result.
     *
     * @param string     $message       Success message.
     * @param int|null   $status_code   HTTP status code.
     * @param float|null $response_time Response time.
     * @param array      $details       Additional details.
     * @return self
     */
    public static function success(
        string $message = 'Connection successful',
        ?int $status_code = 200,
        ?float $response_time = null,
        array $details = []
    ): self {
        return new self( true, $message, $status_code, $response_time, $details );
    }

    /**
     * Create a failed result.
     *
     * @param string   $message     Error message.
     * @param int|null $status_code HTTP status code.
     * @param array    $details     Additional details.
     * @return self
     */
    public static function failure(
        string $message,
        ?int $status_code = null,
        array $details = []
    ): self {
        return new self( false, $message, $status_code, null, $details );
    }

    /**
     * Create a result for missing configuration.
     *
     * @param string $missing_field The field that is missing.
     * @return self
     */
    public static function notConfigured( string $missing_field = '' ): self {
        $message = __( 'Provider is not configured.', 'nowscrobbling' );
        if ( $missing_field ) {
            $message = sprintf(
                /* translators: %s: The missing field name */
                __( 'Missing required field: %s', 'nowscrobbling' ),
                $missing_field
            );
        }
        return new self( false, $message, null, null, [ 'missing_field' => $missing_field ] );
    }

    /**
     * Create a result for rate limit exceeded.
     *
     * @param int $retry_after Seconds until rate limit resets.
     * @return self
     */
    public static function rateLimited( int $retry_after = 0 ): self {
        return new self(
            false,
            __( 'Rate limit exceeded.', 'nowscrobbling' ),
            429,
            null,
            [ 'retry_after' => $retry_after ]
        );
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'success'      => $this->success,
            'message'      => $this->message,
            'status_code'  => $this->statusCode,
            'response_time' => $this->responseTime,
            'details'      => $this->details,
        ];
    }
}
