<?php

namespace Ufz\ApiBase\Service\Geo;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Send Requests to the Geo Api.
 */
interface GeoApiInterface
{
    /**
     * Send a GraphQl Request.
     * If successful, returns json decoded response, otherwise throws.
     * @param string $query graphQl query to perform
     * @param string $authHeader contains Bearer token for auth
     *
     * @throws TransportExceptionInterface
     */
    public function requestGraphQl(string $query, string $authHeader): array;

    /**
     * Resolve locationIds to Coordinates.
     * @param array $locationIds locationIds to fetch coordinates of.
     * @param string $authHeader string containing Bearer token for geo api.
     * @return array array of all coordinates for this location.
     *
     * @throws TransportExceptionInterface
     */
    public function resolveLocationCoordinates(array $locationIds, string $authHeader): array;

    /**
     * Create location in Geo service with GPS tracking points as line.
     * @param array $gpsTrackingPoints Array of points with 'lat' and 'lng' keys
     * @param string $authHeader Authorization header
     * @return int Geo location ID
     *
     * @throws TransportExceptionInterface
     */
    public function createGeoLocationFromGpsPoints(array $gpsTrackingPoints, string $authHeader): int;

    /**
     * Create location in Geo service with a single point (MULTIPOINT geometry).
     * Used for single observations (Einzelfundmeldungen).
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param string $authHeader Authorization header
     * @return int Geo location ID
     *
     * @throws TransportExceptionInterface
     */
    public function createGeoLocationFromPoint(float $lat, float $lng, string $authHeader): int;

    /**
     * create a new geo location as a single point in the geo api
     *
     * @param string|float $lat latitude of the point
     * @param string|float $lng longitude of the point
     * @param string $name name of the location
     * @param int $projectSource source of the project
     * @param string $authHeader authorization header for the request
     * @return array
     */
    public function createGeoLocation(string|float $lat, string|float $lng, string $name, int $projectSource, string $authHeader): array;
}
