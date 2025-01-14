<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

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
                'organization' => null,
                'cpf' => '13754674412',
                'contact' => [
                    'email' => $data['billing']['email'],
                    'mobile' => str_replace('-', '', $data['billing']['phone']),
                ], 
                'address' => [
                    'country' => $data['billing']['country'],
                    'postcode' => $data['billing']['postcode'],
                    'city' => $data['billing']['city'],
                    'street_name' => $data['billing']['address_1'],                  
                ],               
            ];

            $response = $this->client->post('people', [
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
            $client = $this->clientCreated($request);
            $client_id = $client['id'];

            $data = $request->all();

            $orderData = [
                'title' => 'Pedido #' . $data['id'],
                'value' => $data['total'],
                'dealStatusText' => $this->mapDealStatusToAgendor($data['status']),
                'description' => 'Pedido realizado no WooCommerce',
            ];

            $response = $this->client->post("people/$client_id/deals", [
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

    private function getAgendorOrganizationId($email)
    {
        try {
            $response = $this->client->get('organizations', [
                'query' => ['email' => $email]
            ]);

            $organizations = json_decode($response->getBody()->getContents(), true);

            Log::info('getAgendorOrganizationId:', ['response' => $organizations]);

            return $organizations['data'][0]['id'] ?? env('ORGANIZATION_ID');
        } catch (Exception $error) {
            Log::error('getAgendorOrganizationId:', ['error' => $error->getMessage()]);
            return env('ORGANIZATION_ID') ?? null;
        }
    }

    private function getAgendorDealId($wooOrderId)
    {
        try {
            $response = $this->client->get('deals', [
                'query' => ['search' => 'Pedido #' . $wooOrderId]
            ]);

            $deals = json_decode($response->getBody()->getContents(), true);

            return $deals['data'][0]['id'] ?? null;
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