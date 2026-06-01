<?php

namespace Ufz\ApiBase\Service\Geo;

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeoApiService implements GeoApiInterface
{
    public const TMD_PROJECT_SOURCE = 1;
    public const FLOW_PROJECT_SOURCE = 2;
    public const OTTERLAND_PROJECT_SOURCE = 3;

    public const SUPPORTED_PROJECT_SOURCES = [
        self::TMD_PROJECT_SOURCE,
        self::FLOW_PROJECT_SOURCE,
        self::OTTERLAND_PROJECT_SOURCE
    ];

    public const string GRAPHQL = 'graphql';

    public const string QUERY_GEO_LOCATION_BY_ID = '{
        points(location_id_is_list: [%s]) {
            edges {
                node {
                    _id
                    geomJson
                    location {
                        _id
                    }
                }
            }
        }
        lines(location_id_is_list: [%s]) {
            edges {
                node {
                    _id
                    geomJson
                    location {
                        _id
                    }
                }
            }
        }
    }';

    public const string MUTATION_BATCH_CREATE_GEO_LOCATION = <<<'GRAPHQL'
        mutation {
            batchCreateLocation(
                input: {
                    projectSource: %d
                    locationInfo: "%s"
                    create: {
                        points: [{ geom: "%s" }]
                    }
                }
            ) {
                location {
                    id
                    _id
                    uuid
                    locationInfo
                    points {
                        edges {
                            node {
                                id
                                _id
                                geom
                            }
                        }
                    }
                }
            }
        }
    GRAPHQL;

    /**
     * @var HttpClientInterface
     */
    private HttpClientInterface $httpClient;

    /**
     * @var string
     */
    private string $geoEndpoint;

    /**
     * @param string $geoEndpoint
     * @param HttpClientInterface $httpClient
     */
    public function __construct(string $geoEndpoint, HttpClientInterface $httpClient)
    {
        $this->geoEndpoint = $geoEndpoint;
        $this->httpClient = $httpClient;
    }

    /**
     * @param string $query
     * @param string $authHeader
     * @return array
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function requestGraphQl(string $query, string $authHeader): array
    {
        $response = $this->httpClient->request(
            Request::METHOD_POST,
            $this->geoEndpoint . self::GRAPHQL,
            [
                'headers' => ['Content-Type' => 'application/json', 'Authorization' => $authHeader],
                'body' => json_encode(['query' => $query])
            ]
        );

        if ($response->getStatusCode() != Response::HTTP_OK) {
            throw new TransportException('Unsuccessful Request to GeoApi with status: ' . $response->getStatusCode());
        }

        return json_decode($response->getContent(), true);
    }

    /**
     * @param array $locationIds
     * @param string $authHeader
     * @return array with geo data
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function resolveLocationCoordinates(array $locationIds, string $authHeader): array
    {
        if ($locationIds == null) {
            return [];
        }
        $result = [];
        $idChunks = array_chunk(array_unique($locationIds), 100);
        array_walk(
            $idChunks,
            function ($ids) use (&$result, $authHeader) {
                $quotedIds = array_map(fn($id) => '"' . trim(strval($id), '"') . '"', $ids);
                $idsString = implode(",", $quotedIds);
                $json = $this->requestGraphQl(
                    sprintf(
                        self::QUERY_GEO_LOCATION_BY_ID,
                        $idsString,
                        $idsString
                    ),
                    $authHeader
                );
                if ($this->isValidResponse($json)) {
                    $newResult = $this->formatResponse($json);
                    if (count($newResult) != count($ids)) {
                        throw new TransportException(sprintf(
                            'Geo Api Response did not contain enough values. Requested location ids: [%s]. Ids in response: [%s]',
                            implode(",", array_map(fn ($id) => trim($id, '"'), $ids)),
                            implode(",", array_keys($newResult))
                        ));
                    }
                    $result = $result + $newResult;
                } else {
                    throw new TransportException(sprintf('Geo Api Response is in unexpected format: %s.', json_encode($json)));
                }
            }
        );
        return $result;
    }

    /**
     * create a new geo location as a single point in the geo api
     *
     * @param string|float $lat latitude of the point
     * @param string|float $lng longitude of the point
     * @param string $name name of the location
     * @param int $projectSource source of the project
     * @param string $authHeader authorization header for the request
     * @return array
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function createGeoLocation(string|float $lat, string|float $lng, string $name, int $projectSource, string $authHeader): array
    {

        if (!in_array($projectSource, self::SUPPORTED_PROJECT_SOURCES)) {
            throw new TransportException(sprintf('Invalid project source: %d', $projectSource));
        }

        $geom = sprintf('SRID=4326;POINT(%F %F)', $lat, $lng);
        $query = sprintf(
            self::MUTATION_BATCH_CREATE_GEO_LOCATION,
            $projectSource,
            addslashes($name),
            addslashes($geom)
        );

        $json = $this->requestGraphQl($query, $authHeader);

        if (!$this->isValidBatchCreateResponse($json)) {
            throw new TransportException(sprintf('Geo Api Response is in unexpected format: %s.', json_encode($json)));
        }

        return $this->formatBatchCreateResponse($json);
    }

    private function isValidBatchCreateResponse(array $json): bool
    {
        return isset($json['data']['batchCreateLocation']['location']['points']['edges']);
    }


    /**
     * @param array $json
     * @return bool
     */
    private function isValidResponse(array $json): bool
    {
        return isset($json['data']) &&
            (isset($json['data']['lines']) || isset($json['data']['points'])) &&
            (isset($json['data']['lines']['edges']) || isset($json['data']['points']['edges']));
    }

    private function formatBatchCreateResponse(array $json): array
    {
        return $json['data']['batchCreateLocation']['location'];
    }

    /**
     * @param array $json
     * @return array
     * @throws TransportExceptionInterface
     */
    private function formatResponse(array $json): array
    {
        $results = [];
        $points = $json['data']['points']['edges'] ?? [];
        $lines = $json['data']['lines']['edges'] ?? [];
        $edges = array_merge($points, $lines);
        array_walk($edges, function (array $edge) use (&$results) {

            $node = $edge['node'];
            if (!isset($node['location']) || !isset($node['location']['_id'])) {
                throw new TransportException(sprintf("Geo Api Response is invalid: Location id for line/point with id '%d' is null.", (int)$node['_id']));
            }
            $locationId = $node['location']['_id'];
            $geomJson = json_decode($node['geomJson']);
            if (!isset($geomJson)) {
                throw new TransportException(sprintf("Geo Api Response is invalid: GeomJson for location with id '%d' is null.", (int)$locationId));
            }
            if (isset($geomJson->type) && $geomJson->type === 'Point') {
                $results[(int)$locationId] = array_map(fn($coord) => number_format((float)$coord, 6, '.', ''), $geomJson->coordinates);
            } elseif (isset($geomJson->type) && $geomJson->type === 'MultiPoint') {
                $results[(int)$locationId] = array_map(fn($coord) => number_format((float)$coord, 6, '.', ''), $geomJson->coordinates[0]);
            } else {
                $results[(int)$locationId] = array_map(fn($coord) => number_format((float)$coord, 6, '.', ''), $geomJson->coordinates[0][0]);
            }
        });
        return $results;
    }

    /**
     * Create location in Geo service with GPS tracking points as line
     *
     * @param array $gpsTrackingPoints Array of points with 'lat' and 'lng' keys
     * @param string $authHeader Authorization header
     * @return int Geo location ID
     * @throws TransportExceptionInterface
     */
    public function createGeoLocationFromGpsPoints(array $gpsTrackingPoints, string $authHeader): int
    {
        if (empty($gpsTrackingPoints)) {
            throw new \InvalidArgumentException("GPS tracking points are required");
        }

        //added mqu fixes
        //case 1: user input is less than 2 points
        if (count($gpsTrackingPoints) < 2) {
            throw new \InvalidArgumentException("At least 2 GPS tracking points are required");
        }
    
        //case 2: user input is two times the same point
        //This case should be extended to: user input has all the points repeated, then it is a single Point (I will not implement it, but should be done)
        if (count($gpsTrackingPoints) == 2) {
            if ($gpsTrackingPoints[0] == $gpsTrackingPoints[1])  {
                throw new \InvalidArgumentException("At least 2 different GPS tracking points are required");
            }
        }

        foreach ($gpsTrackingPoints as $point){
            //case 4: user input is NaN
            if (!is_numeric($point["lat"]) || !is_numeric($point["lng"])) {
                throw new \InvalidArgumentException("Numerical tracking points are required");
            }

            //case 3: lat or lng out-of-range
            if (abs($point["lat"]) > 90 || abs($point["lng"]) > 180) {
                throw new \InvalidArgumentException("Provided tracking point is out of range");
            }
        }    

        // Build MULTILINESTRING geometry from GPS points
        $lineStringPoints = [];
        foreach ($gpsTrackingPoints as $point) {
            $lineStringPoints[] = "{$point['lng']} {$point['lat']}";
        }
        $lineString = implode(', ', $lineStringPoints);
        $geometry = "SRID=4326;MULTILINESTRING (({$lineString}))";

        // Calculate line length
        $lineLength = $this->calculateLineLength($gpsTrackingPoints);

        // Create mutation for Geo service
        $mutation = sprintf(
            'mutation {
                batchCreateLocation(input: {
                    projectSource: 1,
                    create: {
                        lines: [{
                            geom: "%s",
                            lineLength: "%s"
                        }]
                    }
                }) {
                    location {
                        _id
                        lines {
                            edges {
                                node {
                                    _id
                                }
                            }
                        }
                    }
                }
            }',
            $geometry,
            $lineLength
        );

        try {
            $response = $this->requestGraphQl($mutation, $authHeader);

            if (!isset($response['data']['batchCreateLocation']['location']['_id'])) {
                throw new TransportException("Failed to create geo location: Invalid response from Geo service");
            }

            return (int)$response['data']['batchCreateLocation']['location']['_id'];
        } catch (\Exception $e) {
            throw new TransportException("Failed to create geo location: " . $e->getMessage());
        }
    }

    /**
     * Create location in Geo service with a single point (MULTIPOINT geometry).
     * Used for single observations (Einzelfundmeldungen).
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param string $authHeader Authorization header
     * @return int Geo location ID
     * @throws TransportExceptionInterface
     */
    public function createGeoLocationFromPoint(float $lat, float $lng, string $authHeader): int
    {
        $geom = sprintf('SRID=4326;MULTIPOINT((%F %F))', $lng, $lat);

        $mutation = sprintf(
            self::MUTATION_BATCH_CREATE_GEO_LOCATION,
            self::TMD_PROJECT_SOURCE,
            '',
            addslashes($geom)
        );

        try {
            $response = $this->requestGraphQl($mutation, $authHeader);

            if (!$this->isValidBatchCreateResponse($response)) {
                throw new TransportException("Failed to create geo location from point: Invalid response from Geo service");
            }

            return (int)$response['data']['batchCreateLocation']['location']['_id'];
        } catch (\Exception $e) {
            throw new TransportException("Failed to create geo location from point: " . $e->getMessage());
        }
    }

    /**
     * Calculate line length from GPS points (Haversine formula)
     *
     * @param array $points Array of points with 'lat' and 'lng' keys
     * @return string Line length in meters
     */
    private function calculateLineLength(array $points): string
    {
        if (count($points) < 2) {
            return "0";
        }

        $totalDistance = 0;
        for ($i = 0; $i < count($points) - 1; $i++) {
            $totalDistance += $this->haversineDistance(
                $points[$i]['lat'],
                $points[$i]['lng'],
                $points[$i + 1]['lat'],
                $points[$i + 1]['lng']
            );
        }

        return (string)round($totalDistance, 2);
    }

    /**
     * Calculate distance between two GPS points using Haversine formula
     *
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float Distance in meters
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
