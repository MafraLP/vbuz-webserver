<?php

namespace App\Console\Commands;

use App\Services\RouteCalculationService;
use Illuminate\Console\Command;

class TestRoutingService extends Command
{
    protected $signature = 'routing:test
                          {--mode= : Forçar modo específico (osrm|external)}';

    protected $description = 'Testa a conectividade com o serviço de roteamento';

    public function handle(RouteCalculationService $service)
    {
        $this->info('🚀 Testando Serviço de Roteamento...');
        $this->newLine();

        // Verificar configuração atual
        $useExternal = config('routing.use_external_api', false);
        $mode = $this->option('mode');

        if ($mode) {
            $this->info("🔧 Modo forçado para teste: {$mode}");
            if ($mode === 'external') {
                config(['routing.use_external_api' => true]);
                $useExternal = true;
            } else {
                config(['routing.use_external_api' => false]);
                $useExternal = false;
            }

            // Recrear o service com a nova configuração
            app()->forgetInstance(RouteCalculationService::class);
            $service = new RouteCalculationService();
        }

        $this->table(['Configuração', 'Valor'], [
            ['Modo Atual', $useExternal ? 'API Externa (OpenRouteService)' : 'OSRM Local'],
            ['URL OSRM', config('routing.osrm_url', 'Não configurado')],
            ['URL API Externa', config('routing.external_api_url', 'Não configurado')],
            ['API Key Configurada', config('routing.openroute_service_api_key') ? '✅ Sim' : '❌ Não'],
            ['Timeout', config('routing.request_timeout', 30) . 's'],
        ]);

        $this->newLine();

        // Debug: Mostrar qual serviço será usado
        $this->line("🔍 Serviço que será testado: " . ($useExternal ? 'OpenRouteService' : 'OSRM Local'));

        if ($useExternal && !config('routing.openroute_service_api_key')) {
            $this->error('❌ API Externa selecionada mas API Key não configurada!');
            $this->line('Configure OPENROUTE_SERVICE_API_KEY no arquivo .env');
            return 1;
        }

        // Teste de conectividade
        $this->info('🔍 Testando conectividade...');

        try {
            $result = $service->testConnection();

            if ($result['success']) {
                $this->info('✅ Teste bem-sucedido!');
                $this->line("⏱️  Tempo de resposta: {$result['response_time_ms']}ms");
                $this->line("🔧 Modo usado: {$result['api_mode']}");

                if (isset($result['url'])) {
                    $this->line("🌐 URL: {$result['url']}");
                }

                if ($result['response_time_ms'] > 5000) {
                    $this->warn('⚠️  Tempo de resposta alto (>5s). Considere otimizar.');
                }

            } else {
                $this->error('❌ Teste falhou!');
                $this->error("Erro: {$result['error']}");
                $this->error("Modo: {$result['api_mode']}");

                // Sugestões de solução
                $this->newLine();
                $this->warn('💡 Sugestões:');

                if ($result['api_mode'] === 'osrm') {
                    $this->line('• Verifique se o OSRM está rodando em ' . config('routing.osrm_url'));
                    $this->line('• Para Docker: Verifique se o serviço osrm-backend está up');
                    $this->line('• Para local: Execute docker run -p 5000:5000 osrm/osrm-backend:latest');
                } else {
                    $this->line('• Verifique se OPENROUTE_SERVICE_API_KEY está configurada');
                    $this->line('• Obtenha uma chave em: https://openrouteservice.org/dev/#/signup');
                    $this->line('• Verifique se não excedeu o limite de requisições');
                }
            }

        } catch (\Exception $e) {
            $this->error('💥 Erro inesperado: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();

        // Teste de cálculo real se conectividade OK
        if ($result['success']) {
            $this->info('🧮 Testando cálculo de rota real...');

            try {
                // Criar uma rota de teste temporária
                $testRoute = $this->createTestRoute($service);

                if ($testRoute) {
                    $this->info('✅ Rota de teste criada com sucesso!');
                    $this->line("📏 Distância: {$testRoute['distance']}m");
                    $this->line("⏱️  Duração: {$testRoute['duration']}s");
                } else {
                    $this->warn('⚠️  Não foi possível criar rota de teste');
                }

            } catch (\Exception $e) {
                $this->error('❌ Erro no teste de cálculo: ' . $e->getMessage());
            }
        }

        // Mostrar configuração final se não é um teste forçado
        if (!$mode) {
            $this->newLine();
            $this->info('📝 Para alterar o modo permanentemente:');
            if ($useExternal) {
                $this->line('• Para OSRM Local: ROUTING_USE_EXTERNAL_API=false no .env');
            } else {
                $this->line('• Para API Externa: ROUTING_USE_EXTERNAL_API=true no .env');
                $this->line('• Adicione: OPENROUTE_SERVICE_API_KEY=sua_chave no .env');
            }
        }

        return $result['success'] ? 0 : 1;
    }

    private function createTestRoute(RouteCalculationService $service): ?array
    {
        // Criar objetos mock para teste
        $fromPoint = (object) [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'name' => 'São Paulo'
        ];

        $toPoint = (object) [
            'latitude' => -22.9068,
            'longitude' => -43.1729,
            'name' => 'Rio de Janeiro'
        ];

        $reflection = new \ReflectionClass($service);

        // Usar reflection para acessar método privado
        $method = $reflection->getMethod('calculateSegment');

        try {
            $result = $method->invoke($service, $fromPoint, $toPoint);
            return $result;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
