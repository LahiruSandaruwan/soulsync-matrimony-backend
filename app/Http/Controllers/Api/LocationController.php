<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    /**
     * Get all countries
     */
    public function countries(Request $request): JsonResponse
    {
        try {
            // Cache countries data for 24 hours
            $countries = Cache::remember('countries', 60 * 60 * 24, function () {
                return [
                    ['code' => 'US', 'name' => 'United States', 'flag' => '🇺🇸'],
                    ['code' => 'CA', 'name' => 'Canada', 'flag' => '🇨🇦'],
                    ['code' => 'GB', 'name' => 'United Kingdom', 'flag' => '🇬🇧'],
                    ['code' => 'AU', 'name' => 'Australia', 'flag' => '🇦🇺'],
                    ['code' => 'IN', 'name' => 'India', 'flag' => '🇮🇳'],
                    ['code' => 'LK', 'name' => 'Sri Lanka', 'flag' => '🇱🇰'],
                    ['code' => 'AE', 'name' => 'United Arab Emirates', 'flag' => '🇦🇪'],
                    ['code' => 'SG', 'name' => 'Singapore', 'flag' => '🇸🇬'],
                    ['code' => 'MY', 'name' => 'Malaysia', 'flag' => '🇲🇾'],
                    ['code' => 'DE', 'name' => 'Germany', 'flag' => '🇩🇪'],
                    ['code' => 'FR', 'name' => 'France', 'flag' => '🇫🇷'],
                    ['code' => 'IT', 'name' => 'Italy', 'flag' => '🇮🇹'],
                    ['code' => 'ES', 'name' => 'Spain', 'flag' => '🇪🇸'],
                    ['code' => 'NL', 'name' => 'Netherlands', 'flag' => '🇳🇱'],
                    ['code' => 'JP', 'name' => 'Japan', 'flag' => '🇯🇵'],
                    ['code' => 'KR', 'name' => 'South Korea', 'flag' => '🇰🇷'],
                    ['code' => 'CN', 'name' => 'China', 'flag' => '🇨🇳'],
                    ['code' => 'TH', 'name' => 'Thailand', 'flag' => '🇹🇭'],
                    ['code' => 'PH', 'name' => 'Philippines', 'flag' => '🇵🇭'],
                    ['code' => 'ID', 'name' => 'Indonesia', 'flag' => '🇮🇩'],
                    ['code' => 'VN', 'name' => 'Vietnam', 'flag' => '🇻🇳'],
                    ['code' => 'BD', 'name' => 'Bangladesh', 'flag' => '🇧🇩'],
                    ['code' => 'PK', 'name' => 'Pakistan', 'flag' => '🇵🇰'],
                    ['code' => 'NP', 'name' => 'Nepal', 'flag' => '🇳🇵'],
                    ['code' => 'MM', 'name' => 'Myanmar', 'flag' => '🇲🇲'],
                    ['code' => 'KH', 'name' => 'Cambodia', 'flag' => '🇰🇭'],
                    ['code' => 'LA', 'name' => 'Laos', 'flag' => '🇱🇦'],
                    ['code' => 'BT', 'name' => 'Bhutan', 'flag' => '🇧🇹'],
                    ['code' => 'MV', 'name' => 'Maldives', 'flag' => '🇲🇻'],
                    ['code' => 'BR', 'name' => 'Brazil', 'flag' => '🇧🇷'],
                    ['code' => 'MX', 'name' => 'Mexico', 'flag' => '🇲🇽'],
                    ['code' => 'AR', 'name' => 'Argentina', 'flag' => '🇦🇷'],
                    ['code' => 'CL', 'name' => 'Chile', 'flag' => '🇨🇱'],
                    ['code' => 'CO', 'name' => 'Colombia', 'flag' => '🇨🇴'],
                    ['code' => 'PE', 'name' => 'Peru', 'flag' => '🇵🇪'],
                    ['code' => 'ZA', 'name' => 'South Africa', 'flag' => '🇿🇦'],
                    ['code' => 'EG', 'name' => 'Egypt', 'flag' => '🇪🇬'],
                    ['code' => 'NG', 'name' => 'Nigeria', 'flag' => '🇳🇬'],
                    ['code' => 'KE', 'name' => 'Kenya', 'flag' => '🇰🇪'],
                    ['code' => 'GH', 'name' => 'Ghana', 'flag' => '🇬🇭'],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'countries' => $countries,
                    'total' => count($countries)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get countries',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get states for a specific country
     */
    public function states(Request $request, string $country): JsonResponse
    {
        try {
            $countryCode = strtoupper($country);
            
            // Cache states data for 24 hours
            $cacheKey = "states_{$countryCode}";
            $states = Cache::remember($cacheKey, 60 * 60 * 24, function () use ($countryCode) {
                return $this->getStatesForCountry($countryCode);
            });

            if (empty($states)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No states found for this country',
                    'data' => ['states' => [], 'total' => 0]
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'country' => $countryCode,
                    'states' => $states,
                    'total' => count($states)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get states',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cities for a specific state
     */
    public function cities(Request $request, string $state): JsonResponse
    {
        try {
            $stateCode = strtoupper($state);
            
            // Cache cities data for 24 hours
            $cacheKey = "cities_{$stateCode}";
            $cities = Cache::remember($cacheKey, 60 * 60 * 24, function () use ($stateCode) {
                return $this->getCitiesForState($stateCode);
            });

            if (empty($cities)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No cities found for this state',
                    'data' => ['cities' => [], 'total' => 0]
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'state' => $stateCode,
                    'cities' => $cities,
                    'total' => count($cities)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get cities',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get states for a specific country
     */
    private function getStatesForCountry(string $countryCode): array
    {
        $statesData = [
            'US' => [
                ['code' => 'AL', 'name' => 'Alabama'],
                ['code' => 'AK', 'name' => 'Alaska'],
                ['code' => 'AZ', 'name' => 'Arizona'],
                ['code' => 'AR', 'name' => 'Arkansas'],
                ['code' => 'CA', 'name' => 'California'],
                ['code' => 'CO', 'name' => 'Colorado'],
                ['code' => 'CT', 'name' => 'Connecticut'],
                ['code' => 'DE', 'name' => 'Delaware'],
                ['code' => 'FL', 'name' => 'Florida'],
                ['code' => 'GA', 'name' => 'Georgia'],
                ['code' => 'HI', 'name' => 'Hawaii'],
                ['code' => 'ID', 'name' => 'Idaho'],
                ['code' => 'IL', 'name' => 'Illinois'],
                ['code' => 'IN', 'name' => 'Indiana'],
                ['code' => 'IA', 'name' => 'Iowa'],
                ['code' => 'KS', 'name' => 'Kansas'],
                ['code' => 'KY', 'name' => 'Kentucky'],
                ['code' => 'LA', 'name' => 'Louisiana'],
                ['code' => 'ME', 'name' => 'Maine'],
                ['code' => 'MD', 'name' => 'Maryland'],
                ['code' => 'MA', 'name' => 'Massachusetts'],
                ['code' => 'MI', 'name' => 'Michigan'],
                ['code' => 'MN', 'name' => 'Minnesota'],
                ['code' => 'MS', 'name' => 'Mississippi'],
                ['code' => 'MO', 'name' => 'Missouri'],
                ['code' => 'MT', 'name' => 'Montana'],
                ['code' => 'NE', 'name' => 'Nebraska'],
                ['code' => 'NV', 'name' => 'Nevada'],
                ['code' => 'NH', 'name' => 'New Hampshire'],
                ['code' => 'NJ', 'name' => 'New Jersey'],
                ['code' => 'NM', 'name' => 'New Mexico'],
                ['code' => 'NY', 'name' => 'New York'],
                ['code' => 'NC', 'name' => 'North Carolina'],
                ['code' => 'ND', 'name' => 'North Dakota'],
                ['code' => 'OH', 'name' => 'Ohio'],
                ['code' => 'OK', 'name' => 'Oklahoma'],
                ['code' => 'OR', 'name' => 'Oregon'],
                ['code' => 'PA', 'name' => 'Pennsylvania'],
                ['code' => 'RI', 'name' => 'Rhode Island'],
                ['code' => 'SC', 'name' => 'South Carolina'],
                ['code' => 'SD', 'name' => 'South Dakota'],
                ['code' => 'TN', 'name' => 'Tennessee'],
                ['code' => 'TX', 'name' => 'Texas'],
                ['code' => 'UT', 'name' => 'Utah'],
                ['code' => 'VT', 'name' => 'Vermont'],
                ['code' => 'VA', 'name' => 'Virginia'],
                ['code' => 'WA', 'name' => 'Washington'],
                ['code' => 'WV', 'name' => 'West Virginia'],
                ['code' => 'WI', 'name' => 'Wisconsin'],
                ['code' => 'WY', 'name' => 'Wyoming'],
            ],
            'IN' => [
                ['code' => 'AP', 'name' => 'Andhra Pradesh'],
                ['code' => 'AR', 'name' => 'Arunachal Pradesh'],
                ['code' => 'AS', 'name' => 'Assam'],
                ['code' => 'BR', 'name' => 'Bihar'],
                ['code' => 'CG', 'name' => 'Chhattisgarh'],
                ['code' => 'GA', 'name' => 'Goa'],
                ['code' => 'GJ', 'name' => 'Gujarat'],
                ['code' => 'HR', 'name' => 'Haryana'],
                ['code' => 'HP', 'name' => 'Himachal Pradesh'],
                ['code' => 'JH', 'name' => 'Jharkhand'],
                ['code' => 'KA', 'name' => 'Karnataka'],
                ['code' => 'KL', 'name' => 'Kerala'],
                ['code' => 'MP', 'name' => 'Madhya Pradesh'],
                ['code' => 'MH', 'name' => 'Maharashtra'],
                ['code' => 'MN', 'name' => 'Manipur'],
                ['code' => 'ML', 'name' => 'Meghalaya'],
                ['code' => 'MZ', 'name' => 'Mizoram'],
                ['code' => 'NL', 'name' => 'Nagaland'],
                ['code' => 'OR', 'name' => 'Odisha'],
                ['code' => 'PB', 'name' => 'Punjab'],
                ['code' => 'RJ', 'name' => 'Rajasthan'],
                ['code' => 'SK', 'name' => 'Sikkim'],
                ['code' => 'TN', 'name' => 'Tamil Nadu'],
                ['code' => 'TG', 'name' => 'Telangana'],
                ['code' => 'TR', 'name' => 'Tripura'],
                ['code' => 'UP', 'name' => 'Uttar Pradesh'],
                ['code' => 'UT', 'name' => 'Uttarakhand'],
                ['code' => 'WB', 'name' => 'West Bengal'],
                ['code' => 'AN', 'name' => 'Andaman and Nicobar Islands'],
                ['code' => 'CH', 'name' => 'Chandigarh'],
                ['code' => 'DN', 'name' => 'Dadra and Nagar Haveli and Daman and Diu'],
                ['code' => 'DL', 'name' => 'Delhi'],
                ['code' => 'JK', 'name' => 'Jammu and Kashmir'],
                ['code' => 'LA', 'name' => 'Ladakh'],
                ['code' => 'LD', 'name' => 'Lakshadweep'],
                ['code' => 'PY', 'name' => 'Puducherry'],
            ],
            'LK' => [
                ['code' => 'WP', 'name' => 'Western Province'],
                ['code' => 'CP', 'name' => 'Central Province'],
                ['code' => 'SP', 'name' => 'Southern Province'],
                ['code' => 'NP', 'name' => 'Northern Province'],
                ['code' => 'EP', 'name' => 'Eastern Province'],
                ['code' => 'NWP', 'name' => 'North Western Province'],
                ['code' => 'NC', 'name' => 'North Central Province'],
                ['code' => 'UP', 'name' => 'Uva Province'],
                ['code' => 'SP', 'name' => 'Sabaragamuwa Province'],
            ],
            'CA' => [
                ['code' => 'AB', 'name' => 'Alberta'],
                ['code' => 'BC', 'name' => 'British Columbia'],
                ['code' => 'MB', 'name' => 'Manitoba'],
                ['code' => 'NB', 'name' => 'New Brunswick'],
                ['code' => 'NL', 'name' => 'Newfoundland and Labrador'],
                ['code' => 'NS', 'name' => 'Nova Scotia'],
                ['code' => 'ON', 'name' => 'Ontario'],
                ['code' => 'PE', 'name' => 'Prince Edward Island'],
                ['code' => 'QC', 'name' => 'Quebec'],
                ['code' => 'SK', 'name' => 'Saskatchewan'],
                ['code' => 'NT', 'name' => 'Northwest Territories'],
                ['code' => 'NU', 'name' => 'Nunavut'],
                ['code' => 'YT', 'name' => 'Yukon'],
            ],
            'AU' => [
                ['code' => 'NSW', 'name' => 'New South Wales'],
                ['code' => 'QLD', 'name' => 'Queensland'],
                ['code' => 'SA', 'name' => 'South Australia'],
                ['code' => 'TAS', 'name' => 'Tasmania'],
                ['code' => 'VIC', 'name' => 'Victoria'],
                ['code' => 'WA', 'name' => 'Western Australia'],
                ['code' => 'ACT', 'name' => 'Australian Capital Territory'],
                ['code' => 'NT', 'name' => 'Northern Territory'],
            ],
            'GB' => [
                ['code' => 'ENG', 'name' => 'England'],
                ['code' => 'SCT', 'name' => 'Scotland'],
                ['code' => 'WLS', 'name' => 'Wales'],
                ['code' => 'NIR', 'name' => 'Northern Ireland'],
            ],
        ];

        return $statesData[$countryCode] ?? [];
    }

    /**
     * Get cities for a specific state
     */
    private function getCitiesForState(string $stateCode): array
    {
        $citiesData = [
            // US States
            'CA' => [
                ['name' => 'Los Angeles', 'population' => 3898747],
                ['name' => 'San Diego', 'population' => 1386932],
                ['name' => 'San Jose', 'population' => 1013240],
                ['name' => 'San Francisco', 'population' => 873965],
                ['name' => 'Fresno', 'population' => 525010],
                ['name' => 'Sacramento', 'population' => 495234],
                ['name' => 'Long Beach', 'population' => 462628],
                ['name' => 'Oakland', 'population' => 419267],
                ['name' => 'Bakersfield', 'population' => 373640],
                ['name' => 'Anaheim', 'population' => 350742],
            ],
            'NY' => [
                ['name' => 'New York City', 'population' => 8175133],
                ['name' => 'Buffalo', 'population' => 278349],
                ['name' => 'Rochester', 'population' => 211328],
                ['name' => 'Yonkers', 'population' => 195976],
                ['name' => 'Syracuse', 'population' => 148620],
                ['name' => 'Albany', 'population' => 97856],
                ['name' => 'New Rochelle', 'population' => 77062],
                ['name' => 'Mount Vernon', 'population' => 67292],
                ['name' => 'Schenectady', 'population' => 65273],
                ['name' => 'Utica', 'population' => 62235],
            ],
            'TX' => [
                ['name' => 'Houston', 'population' => 2320268],
                ['name' => 'San Antonio', 'population' => 1547253],
                ['name' => 'Dallas', 'population' => 1343573],
                ['name' => 'Austin', 'population' => 964254],
                ['name' => 'Fort Worth', 'population' => 909585],
                ['name' => 'El Paso', 'population' => 681728],
                ['name' => 'Arlington', 'population' => 398854],
                ['name' => 'Corpus Christi', 'population' => 326586],
                ['name' => 'Plano', 'population' => 288061],
                ['name' => 'Lubbock', 'population' => 258862],
            ],
            
            // Indian States
            'MH' => [
                ['name' => 'Mumbai', 'population' => 12442373],
                ['name' => 'Pune', 'population' => 3124458],
                ['name' => 'Nagpur', 'population' => 2497777],
                ['name' => 'Thane', 'population' => 1818872],
                ['name' => 'Nashik', 'population' => 1486973],
                ['name' => 'Aurangabad', 'population' => 1175116],
                ['name' => 'Solapur', 'population' => 951118],
                ['name' => 'Bhiwandi', 'population' => 709665],
                ['name' => 'Amravati', 'population' => 647057],
                ['name' => 'Nanded', 'population' => 550564],
            ],
            'KA' => [
                ['name' => 'Bangalore', 'population' => 8443675],
                ['name' => 'Mysore', 'population' => 887446],
                ['name' => 'Hubli-Dharwad', 'population' => 943857],
                ['name' => 'Mangalore', 'population' => 623841],
                ['name' => 'Belgaum', 'population' => 610350],
                ['name' => 'Gulbarga', 'population' => 543147],
                ['name' => 'Davanagere', 'population' => 435128],
                ['name' => 'Bellary', 'population' => 410445],
                ['name' => 'Bijapur', 'population' => 327427],
                ['name' => 'Shimoga', 'population' => 322650],
            ],
            'TN' => [
                ['name' => 'Chennai', 'population' => 4681087],
                ['name' => 'Coimbatore', 'population' => 1061447],
                ['name' => 'Madurai', 'population' => 1017865],
                ['name' => 'Tiruchirappalli', 'population' => 847387],
                ['name' => 'Salem', 'population' => 826267],
                ['name' => 'Tirunelveli', 'population' => 474838],
                ['name' => 'Tiruppur', 'population' => 444352],
                ['name' => 'Vellore', 'population' => 423425],
                ['name' => 'Thoothukudi', 'population' => 237817],
                ['name' => 'Nagercoil', 'population' => 224849],
            ],
            
            // Sri Lankan Provinces
            'WP' => [
                ['name' => 'Colombo', 'population' => 752993],
                ['name' => 'Dehiwala-Mount Lavinia', 'population' => 245974],
                ['name' => 'Moratuwa', 'population' => 207755],
                ['name' => 'Sri Jayawardenepura Kotte', 'population' => 115826],
                ['name' => 'Kelaniya', 'population' => 109467],
                ['name' => 'Negombo', 'population' => 142136],
                ['name' => 'Gampaha', 'population' => 57071],
                ['name' => 'Kalutara', 'population' => 42984],
                ['name' => 'Panadura', 'population' => 50000],
                ['name' => 'Wattala', 'population' => 50000],
            ],
            'CP' => [
                ['name' => 'Kandy', 'population' => 125351],
                ['name' => 'Matale', 'population' => 39405],
                ['name' => 'Nuwara Eliya', 'population' => 28196],
                ['name' => 'Gampola', 'population' => 25681],
                ['name' => 'Hatton', 'population' => 15000],
                ['name' => 'Dambulla', 'population' => 12845],
                ['name' => 'Nawalapitiya', 'population' => 11687],
                ['name' => 'Talawakele', 'population' => 10961],
                ['name' => 'Wattegama', 'population' => 9876],
                ['name' => 'Kadugannawa', 'population' => 8500],
            ],
            'SP' => [
                ['name' => 'Galle', 'population' => 99478],
                ['name' => 'Matara', 'population' => 58285],
                ['name' => 'Hambantota', 'population' => 11200],
                ['name' => 'Tangalle', 'population' => 9611],
                ['name' => 'Ambalangoda', 'population' => 22293],
                ['name' => 'Bentota', 'population' => 37000],
                ['name' => 'Hikkaduwa', 'population' => 11500],
                ['name' => 'Weligama', 'population' => 15000],
                ['name' => 'Akuressa', 'population' => 7500],
                ['name' => 'Deniyaya', 'population' => 5000],
            ],
        ];

        return $citiesData[$stateCode] ?? [];
    }
} 