<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function clientCreated(Request $request){
        try{
            $data = $request->all();
    
            $clientData = [
                'name' => $data['billing']['first_name'] . ' ' . $data['billing']['last_name'],
                'email' => $data['billing']['email'],
                'phone' => $data['billing']['phone'],
            ];
    
            $response = Http::withToken(env('AGENDOR_API_TOKEN'))
                ->post('https://api.agendor.com.br/v3/organizations', $clientData);

            if ($response->failed()) {
                throw new Exception(json_encode($response->body()));                
            }
            
            $dataResponse = $response->json();
            Log::info('clientCreated:', ['error' => json_encode($dataResponse)]);

            return response()->json($dataResponse, $response->status());
        }catch(Exception $error){
            Log::error('clientCreated:', ['error' => $error->getMessage()]);
            return response()->json(['error' => 'Erro ao criar cliente no Agendor'], 500);
        }
    }

    public function orderCreated(Request $request)
    {
        try{
            $data = $request->all();

            $orderData = [
                'title' => 'Pedido #' . $data['id'],
                'value' => $data['total'],
                'description' => 'Pedido realizado no WooCommerce',
                'organizationId' => $this->getAgendorOrganizationId($data['billing']['email']),
                'customFields' => [
                    'status' => $this->mapStatusToAgendor($data['status'])
                ],
            ];

            $response = Http::withToken(env('AGENDOR_API_TOKEN'))
                ->post('https://api.agendor.com.br/v3/deals', $orderData);

            if ($response->failed()) {
                throw new Exception(json_encode($response->body()));                
            }
    
            $dataResponse = $response->json();
            Log::info('orderCreated:', ['error' => json_encode($dataResponse)]);

            return response()->json($dataResponse, $response->status());
        }catch(Exception $error){
            Log::error('orderCreated:', ['error' => $error->getMessage()]);
            return response()->json(['error' => 'Erro ao criar pedido no Agendor'], 500);
        }
    }

    public function orderUpdated(Request $request)
    {
        try{
            $data = $request->all();

            $dealId = $this->getAgendorDealId($data['id']);

            if (!$dealId) {
                return response()->json(['error' => 'Negócio não encontrado no Agendor'], 404);
            }

            $updateData = [
                'customFields' => [
                    'status' => $this->mapStatusToAgendor($data['status'])
                ]
            ];

            $response = Http::withToken(env('AGENDOR_API_TOKEN'))
                ->put("https://api.agendor.com.br/v3/deals/{$dealId}", $updateData);

            if ($response->failed()) {
                throw new Exception(json_encode($response->body()));                
            }
    
            $dataResponse = $response->json();
            Log::info('orderUpdated:', ['error' => json_encode($dataResponse)]);

            return response()->json($dataResponse, $response->status());
        }catch(Exception $error){
            Log::error('orderUpdated:', ['error' => $error->getMessage()]);
            return response()->json(['error' => 'Erro ao atualizar pedido no Agendor'], 500);
        }
    }

    private function getAgendorOrganizationId($email)
    {
        try{
        
            $response = Http::withToken(env('AGENDOR_API_TOKEN'))
                ->get('https://api.agendor.com.br/v3/organizations', [
                    'email' => $email,
                ]);

            if ($response->failed()) {
                throw new Exception(json_encode($response->body()));                
            }

            $organizations = $response->json();
            Log::info('getAgendorOrganizationId:', ['error' => json_encode($organizations)]);
            
            return $organizations['data'][0]['id'] ?? env('ORGANIZATION_ID');
        }catch(Exception $error){
            Log::error('getAgendorOrganizationId:', ['error' => $error->getMessage()]);
            return env('ORGANIZATION_ID') ?? null;
        }
    }

    private function getAgendorDealId($wooOrderId)
    {
        try{
            $response = Http::withToken(env('AGENDOR_API_TOKEN'))
                ->get('https://api.agendor.com.br/v3/deals', [
                    'search' => 'Pedido #' . $wooOrderId
                ]);

            if ($response->failed()) {
                throw new Exception(json_encode($response->body()));                
            }

            $deals = $response->json();
            return $deals['data'][0]['id'] ?? null;

        }catch(Exception $error){
            Log::error('getAgendorDealId:', ['error' => $error->getMessage()]);
            return response()->json(['error' => 'Erro ao criar cliente no Agendor'], 500);
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
}
