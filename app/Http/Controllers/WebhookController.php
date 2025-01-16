<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use PHPUnit\TextUI\Configuration\Merger;

class WebhookController extends Controller
{
    private $client;

    public function __construct()
    {
        $apiKey = env('AGENDOR_API_TOKEN');

        $this->client = new Client([
            'base_uri' => 'https://api.agendor.com.br/v3/',
            'headers' => [
                'Authorization' => 'Token ' . $apiKey,
            ],
        ]);
    }

    public function clientCreated(Request $request)
    {
        try {
            $data = $request->all();

            $clientData = [
                'name' => $data['billing']['first_name'] . ' ' . $data['billing']['last_name'],
                'birthday' =>  $data['billing']['birthdate'],
                'cpf' => preg_replace('/\D/', '', $data['billing']['cpf']),
                'contact' => [
                    'email' => $data['billing']['email'],
                    'mobile' => str_replace('-', '', $data['billing']['phone']),
                ], 
                'address' => [
                    'country' => $data['billing']['country'] ?? null,
                    'district' => $data['billing']['neighborhood'] ?? null,
                    'street_number' => $data['billing']['number'] ?? null,
                    'additional_info' => $data['billing']['address_2'] ?? null,
                    'postal_code' => $data['billing']['postcode'] ?? '',
                    'city' => $data['billing']['city'] ?? null,
                    'street_name' => $data['billing']['address_1'] ?? null,
                ],               
            ];

            $response = $this->client->post('people/upsert', [
                'json' => $clientData
            ]);

            $dataResponse = json_decode($response->getBody()->getContents(), true);

            if (!isset($dataResponse['data']['id'])) {
                throw new Exception('Não foi possível recuperar dados do cliente');
            }

            Log::info('clientCreated:', ['response' => $dataResponse]);

            return $dataResponse['data'];
        } catch (Exception $error) {
            Log::error('clientCreated:', ['error' => $error->getMessage()]);
            return ['error' => $error->getMessage()];
        }
    }

    public function organizationCreate(Request $request)
    {
        try {
            $data = $request->all();

            $clientData = [
                'name' => $data['billing']['company'],
                'legalName' => $data['billing']['company'],
                'birthday' =>  $data['billing']['birthdate'],
                'cnpj' => preg_replace('/\D/', '', $data['billing']['cnpj']),
                'contact' => [
                    'email' => $data['billing']['email'],
                    'mobile' => str_replace('-', '', $data['billing']['phone']),
                ], 
                'address' => [
                    'country' => $data['billing']['country'] ?? null,
                    'district' => $data['billing']['neighborhood'] ?? null,
                    'street_number' => $data['billing']['number'] ?? null,
                    'additional_info' => $data['billing']['address_2'] ?? null,
                    'postal_code' => $data['billing']['postcode'] ?? null,
                    'city' => $data['billing']['city'] ?? null,
                    'street_name' => $data['billing']['address_1'] ?? null,
                ],               
            ];

            $response = $this->client->post('organizations/upsert', [
                'json' => $clientData
            ]);

            $dataResponse = json_decode($response->getBody()->getContents(), true);

            if (!isset($dataResponse['data']['id'])) {
                throw new Exception('Não foi possível recuperar dados do cliente');
            }

            Log::info('clientCreated:', ['response' => $dataResponse]);

            return $dataResponse['data'];
        } catch (Exception $error) {
            Log::error('clientCreated:', ['error' => $error->getMessage()]);
            return response()->json(['error' => $error->getMessage()], 200);
        }
    }

    public function orderCreated(Request $request)
    {
        try {
            $result = match (true) {
                isset($request['billing']['cpf']) => [$this->clientCreated($request), 'people'],
                isset($request['billing']['cnpj']) => [$this->organizationCreate($request), 'organizations'],
                default => [null],
            };

            if(! $result[0]){
                throw new Exception('Cliente não encontrado');
            }
                        
            $client_id = $result[0]['id'];

            $data = $request->all();

            $orderData = [
                'title' => 'Pedido #' . $data['id'],
                'value' => $data['total'],
                'dealStatusText' => $this->mapDealStatusToAgendor($data['status']),
                'description' => 'Pedido realizado no WooCommerce',
            ];

            $response = $this->client->post("{$result[1]}/$client_id/deals", [
                'json' => $orderData
            ]);

            $dataResponse = json_decode($response->getBody()->getContents(), true);

            Log::info('orderCreated:', ['response' => $dataResponse]);

            return response()->json($dataResponse, $response->getStatusCode());
        } catch (Exception $error) {
            Log::error('orderCreated:', ['error' => $error->getMessage()]);
            return response()->json(['error' => $error->getMessage()], 200);
        }
    }

    public function orderUpdated(Request $request)
    {
        try {
            $data = $request->all();

            $dealId = $this->getAgendorDealId($data['id']);

            if (!$dealId) {
                return response()->json(['error' => 'Negócio não encontrado no Agendor'], 404);
            }

            $updateData = [
                'dealStatusText' => $this->mapDealStatusToAgendor($data['status']),
            ];

            $response = $this->client->put("deals/{$dealId}/status", [
                'json' => $updateData
            ]);

            $dataResponse = json_decode($response->getBody()->getContents(), true);

            Log::info('orderUpdated:', ['response' => $dataResponse]);

            return response()->json($dataResponse, $response->getStatusCode());
        } catch (Exception $error) {
            Log::error('orderUpdated:', ['error' => $error->getMessage()]);
            return response()->json(['error' => $error->getMessage()], 200);
        }
    }

    private function getAgendorDealId($wooOrderId)
    {
        try {
            $orderTitle = 'Pedido #' . $wooOrderId;
            
            $response = $this->client->get('deals', [
                'query' => ['search' => $orderTitle]
            ]);

            $deals = json_decode($response->getBody()->getContents(), true);

            foreach ($deals['data'] as $deal) {
                if (isset($deal['title']) && $deal['title'] === $orderTitle) {
                    return $deal['id'];
                }
            }

            return null;
        } catch (Exception $error) {
            Log::error('getAgendorDealId:', ['error' => $error->getMessage()]);
            return null;
        }
    }

    private function mapStatusToAgendor($wooStatus)
    {
        $statusMap = [
            'pending' => 'Aguardando pagamento',
            'processing' => 'Pagamento em análise',
            'completed' => 'Pago',
            'on-hold' => 'Em espera',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            'failed' => 'Falhou'
        ];

        return $statusMap[$wooStatus] ?? 'Status desconhecido';
    }

    private function mapDealStatusToAgendor($wooStatus)
    {
        $statusMap = [
            'processing' => 'ongoing',
            'on-hold' => 'ongoing',
            'completed' => 'won',
            'cancelled' => 'lost',
            'refunded' => 'lost',
            'failed' => 'lost'
        ];

        return $statusMap[$wooStatus] ?? 'ongoing';
    }
}