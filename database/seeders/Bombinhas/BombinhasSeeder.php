<?php

namespace Database\Seeders\Bombinhas;

use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\Users\User;
use App\Models\Institution;
use App\Models\Permission;
use App\Models\Routes\Route;
use App\Models\Routes\RoutePoint;

class BombinhasSeeder extends Seeder
{
    /**
     * Coordenadas centrais de Bombinhas - SC
     */
    const BOMBINHAS_LAT = -27.1364;
    const BOMBINHAS_LNG = -48.5147;

    // Arrays para armazenar IDs de usuários por tipo
    private $adminIds = [];
    private $managerIds = [];
    private $driverIds = [];
    private $passengerIds = [];

    // Arrays para armazenar IDs de instituições
    private $prefectureId = null;
    private $schoolIds = [];
    private $departmentIds = [];

    // Arrays para armazenar IDs de permissões
    private $permissionIds = [];
    private $routeIds = [];

    /**
     * Roda o seeder com dados específicos para Bombinhas - SC
     */
    public function run()
    {
        $this->command->info('Iniciando seed de dados para Bombinhas - SC');

        try {
            // Criar usuários de base
            $this->seedUsers();
            $this->command->info('✅ Usuários criados com sucesso');

            // Criar instituições para Bombinhas
            $this->seedInstitutions();
            $this->command->info('✅ Instituições criadas com sucesso');

            // 🎫 IMPORTANTE: Criar permissões SEMPRE (mesmo que outras tabelas não existam)
            $this->seedPermissions();
            $this->command->info('✅ Permissões processadas');

            // Criar perfis de usuários se as tabelas existirem
            if (Schema::hasTable('driver_profiles') &&
                Schema::hasTable('manager_profiles') &&
                Schema::hasTable('passenger_profiles')) {
                $this->seedProfiles();
                $this->command->info('✅ Perfis criados com sucesso');
            } else {
                $this->command->warn('⚠️  Tabelas de perfis não encontradas. Pulando criação de perfis.');
            }

            // Estabelecer as relações, se as tabelas existirem
            if (Schema::hasTable('driver_institution') &&
                Schema::hasTable('manager_institution') &&
                Schema::hasTable('passenger_institution') &&
                Schema::hasTable('institution_user')) {
                $this->seedRelationships();
                $this->command->info('✅ Relacionamentos criados com sucesso');
            } else {
                $this->command->warn('⚠️  Tabelas de relacionamentos não encontradas. Pulando criação de relacionamentos.');
            }

            // Criar pontos para Bombinhas se a tabela existir
            if (Schema::hasTable('points')) {
                $this->seedPoints();
                $this->command->info('✅ Pontos criados com sucesso');
            } else {
                $this->command->warn('⚠️  Tabela points não encontrada. Pulando criação de pontos.');
            }

            // Criar rotas se a tabela existir
            if (Schema::hasTable('routes') && Schema::hasTable('route_points')) {
                $this->seedRoutes();
                $this->command->info('✅ Rotas criadas com sucesso');
            } else {
                $this->command->warn('⚠️  Tabelas de rotas não encontradas. Pulando criação de rotas.');
            }

            // 🎫 Associar permissões aos usuários e rotas (sempre tentar)
            if (!empty($this->permissionIds)) {
                $this->seedUserPermissions();
                $this->command->info('✅ Permissões de usuários associadas');

                $this->seedRoutePermissions();
                $this->command->info('✅ Permissões de rotas associadas');
            } else {
                $this->command->warn('⚠️  Nenhuma permissão foi criada. Pulando associações.');
            }

            $this->command->info('🎉 Dados de Bombinhas - SC criados com sucesso!');
            $this->printSeedSummary();

        } catch (Exception $e) {
            $this->command->error('❌ Erro durante o seed: ' . $e->getMessage());
            $this->command->error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Imprime um resumo do que foi criado
     */
    private function printSeedSummary()
    {
        $this->command->info('');
        $this->command->info('📊 === RESUMO DO SEED ===');
        $this->command->info('👥 Usuários criados:');
        $this->command->info('   - Admins: ' . count($this->adminIds));
        $this->command->info('   - Gerentes: ' . count($this->managerIds));
        $this->command->info('   - Motoristas: ' . count($this->driverIds));
        $this->command->info('   - Passageiros: ' . count($this->passengerIds));
        $this->command->info('');
        $this->command->info('🏢 Instituições criadas:');
        $this->command->info('   - Prefeitura: ' . ($this->prefectureId ? 'ID ' . $this->prefectureId : 'Não criada'));
        $this->command->info('   - Escolas: ' . count($this->schoolIds));
        $this->command->info('   - Departamentos: ' . count($this->departmentIds));
        $this->command->info('');
        $this->command->info('🎫 Permissões criadas: ' . count($this->permissionIds));
        if (!empty($this->permissionIds)) {
            foreach ($this->permissionIds as $code => $id) {
                $this->command->info("   - {$code}: ID {$id}");
            }
        }
        $this->command->info('');
        $this->command->info('🚌 Rotas criadas: ' . count($this->routeIds));
        $this->command->info('');
    }

    /**
     * Seed de usuários base
     */
    private function seedUsers()
    {
        $this->command->info('Criando usuários...');

        // Admin
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@bombinhas.sc.gov.br',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->adminIds[] = $admin->id;

        // Gerentes
        $managers = [
            [
                'name' => 'João Silva',
                'email' => 'joao.silva@bombinhas.sc.gov.br',
            ],
            [
                'name' => 'Maria Oliveira',
                'email' => 'maria.oliveira@bombinhas.sc.gov.br',
            ],
        ];

        foreach ($managers as $manager) {
            $newManager = User::create([
                'name' => $manager['name'],
                'email' => $manager['email'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->managerIds[] = $newManager->id;
        }

        // Motoristas
        $drivers = [
            [
                'name' => 'Carlos Santos',
                'email' => 'carlos.santos@bombinhas.sc.gov.br',
            ],
            [
                'name' => 'Roberto Pereira',
                'email' => 'roberto.pereira@bombinhas.sc.gov.br',
            ],
            [
                'name' => 'José Ferreira',
                'email' => 'jose.ferreira@bombinhas.sc.gov.br',
            ],
        ];

        foreach ($drivers as $driver) {
            $newDriver = User::create([
                'name' => $driver['name'],
                'email' => $driver['email'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->driverIds[] = $newDriver->id;
        }

        // Passageiros (estudantes)
        $passengers = [
            [
                'name' => 'Ana Souza',
                'email' => 'ana.souza@estudante.bombinhas.sc.gov.br',
            ],
            [
                'name' => 'Pedro Lima',
                'email' => 'pedro.lima@estudante.bombinhas.sc.gov.br',
            ],
            [
                'name' => 'Julia Costa',
                'email' => 'julia.costa@estudante.bombinhas.sc.gov.br',
            ],
            [
                'name' => 'Mateus Alves',
                'email' => 'mateus.alves@estudante.bombinhas.sc.gov.br',
            ],
            [
                'name' => 'Gabriela Martins',
                'email' => 'gabriela.martins@estudante.bombinhas.sc.gov.br',
            ],
        ];

        foreach ($passengers as $passenger) {
            $newPassenger = User::create([
                'name' => $passenger['name'],
                'email' => $passenger['email'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->passengerIds[] = $newPassenger->id;
        }
    }

    /**
     * Seed de instituições
     */
    private function seedInstitutions()
    {
        $this->command->info('Criando instituições...');

        // Prefeitura - usar apenas colunas que existem na tabela
        $prefecture = Institution::create([
            'name' => 'Prefeitura Municipal de Bombinhas',
            'type' => 'prefecture',
            'city' => 'Bombinhas',
            'state' => 'SC',
            'address' => 'Av. Baleia Jubarte, 328',
            'latitude' => self::BOMBINHAS_LAT,
            'longitude' => self::BOMBINHAS_LNG,
            'notes' => 'Prefeitura Municipal de Bombinhas - SC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->prefectureId = $prefecture->id;

        // Escolas
        $schools = [
            [
                'name' => 'Escola Municipal Edith Willard',
                'address' => 'Rua Pardela, 111 - Bombas',
                'latitude' => -27.1467,
                'longitude' => -48.5228,
            ],
            [
                'name' => 'Escola Básica Municipal Manoel José Antônio',
                'address' => 'Avenida Leopoldo Zarling, 196 - Centro',
                'latitude' => -27.1380,
                'longitude' => -48.5154,
            ],
            [
                'name' => 'Escola Municipal Pequeno Príncipe',
                'address' => 'Rua Araribá, 25 - Bombas',
                'latitude' => -27.1499,
                'longitude' => -48.5146,
            ],
        ];

        foreach ($schools as $school) {
            $newSchool = Institution::create([
                'name' => $school['name'],
                'type' => 'school',
                'city' => 'Bombinhas',
                'state' => 'SC',
                'address' => $school['address'],
                'latitude' => $school['latitude'],
                'longitude' => $school['longitude'],
                'parent_id' => $this->prefectureId,
                'notes' => 'Escola Municipal em Bombinhas',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->schoolIds[] = $newSchool->id;
        }

        // Departamentos
        $departments = [
            [
                'name' => 'Secretaria de Educação de Bombinhas',
                'address' => 'Av. Baleia Jubarte, 328 - Centro',
                'latitude' => self::BOMBINHAS_LAT,
                'longitude' => self::BOMBINHAS_LNG,
            ],
            [
                'name' => 'Secretaria de Saúde de Bombinhas',
                'address' => 'Avenida Vereador Manoel dos Santos, 520 - Centro',
                'latitude' => -27.1376,
                'longitude' => -48.5157,
            ],
        ];

        foreach ($departments as $department) {
            $newDepartment = Institution::create([
                'name' => $department['name'],
                'type' => 'department',
                'city' => 'Bombinhas',
                'state' => 'SC',
                'address' => $department['address'],
                'latitude' => $department['latitude'],
                'longitude' => $department['longitude'],
                'parent_id' => $this->prefectureId,
                'notes' => 'Departamento da Prefeitura de Bombinhas',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->departmentIds[] = $newDepartment->id;
        }
    }

    /**
     * Seed de permissões/carteirinhas
     */
    private function seedPermissions()
    {
        $this->command->info('Criando permissões/carteirinhas...');

        // Verificar se a tabela existe
        if (!Schema::hasTable('permissions')) {
            $this->command->warn('Tabela permissions não encontrada. Pulando criação de permissões.');
            return;
        }

        $permissions = [
            // Permissões Administrativas
            [
                'name' => 'Administrador Geral',
                'code' => 'ADMIN_GERAL',
                'description' => 'Acesso total ao sistema - todos os módulos e funcionalidades',
                'color' => '#dc3545',
                'icon' => 'shield-check',
                'institution_id' => $this->prefectureId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Gerente de Transportes',
                'code' => 'GERENTE_TRANSP',
                'description' => 'Gerenciamento completo de rotas, veículos e motoristas',
                'color' => '#007bff',
                'icon' => 'truck',
                'institution_id' => $this->prefectureId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Supervisor Escolar',
                'code' => 'SUPERVISOR_ESC',
                'description' => 'Supervisão de rotas escolares e estudantes',
                'color' => '#28a745',
                'icon' => 'graduation-cap',
                'institution_id' => $this->prefectureId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Permissões Operacionais
            [
                'name' => 'Motorista Autorizado',
                'code' => 'MOTORISTA_AUT',
                'description' => 'Autorização para conduzir veículos oficiais do município',
                'color' => '#ffc107',
                'icon' => 'car',
                'institution_id' => $this->prefectureId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Transporte Escolar',
                'code' => 'TRANSP_ESCOLAR',
                'description' => 'Acesso ao sistema de transporte escolar',
                'color' => '#17a2b8',
                'icon' => 'bus',
                'institution_id' => $this->prefectureId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Rota Prioritária',
                'code' => 'ROTA_PRIORIT',
                'description' => 'Acesso a rotas com prioridade especial',
                'color' => '#6f42c1',
                'icon' => 'star',
                'institution_id' => $this->prefectureId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Permissões de Estudantes
            [
                'name' => 'Estudante Regular',
                'code' => 'ESTUDANTE_REG',
                'description' => 'Carteirinha de estudante para transporte regular',
                'color' => '#20c997',
                'icon' => 'user-graduate',
                'institution_id' => $this->prefectureId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Necessidades Especiais',
                'code' => 'NECESSID_ESP',
                'description' => 'Atendimento especializado para estudantes com necessidades especiais',
                'color' => '#fd7e14',
                'icon' => 'wheelchair',
                'institution_id' => $this->prefectureId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Permissões Específicas por Escola
            [
                'name' => 'Escola Edith Willard',
                'code' => 'ESC_EDITH',
                'description' => 'Acesso específico para Escola Municipal Edith Willard',
                'color' => '#e83e8c',
                'icon' => 'school',
                'institution_id' => $this->schoolIds[0] ?? $this->prefectureId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Escola Manoel José',
                'code' => 'ESC_MANOEL',
                'description' => 'Acesso específico para Escola Básica Municipal Manoel José Antônio',
                'color' => '#6610f2',
                'icon' => 'school',
                'institution_id' => $this->schoolIds[1] ?? $this->prefectureId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Escola Pequeno Príncipe',
                'code' => 'ESC_PEQUENO',
                'description' => 'Acesso específico para Escola Municipal Pequeno Príncipe',
                'color' => '#6f42c1',
                'icon' => 'school',
                'institution_id' => $this->schoolIds[2] ?? $this->prefectureId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Permissões Temporárias/Especiais
            [
                'name' => 'Visitante Autorizado',
                'code' => 'VISITANTE_AUT',
                'description' => 'Acesso temporário para visitantes e acompanhantes',
                'color' => '#6c757d',
                'icon' => 'user-check',
                'institution_id' => $this->prefectureId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Emergência Médica',
                'code' => 'EMERGENCIA_MED',
                'description' => 'Prioridade para situações de emergência médica',
                'color' => '#dc3545',
                'icon' => 'heart-pulse',
                'institution_id' => $this->prefectureId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Verificar se temos instituições criadas
        if (!$this->prefectureId) {
            $this->command->error('Prefeitura não foi criada. Não é possível criar permissões.');
            return;
        }

        $createdCount = 0;
        foreach ($permissions as $permissionData) {
            try {
                // Verificar se a permissão já existe (evitar duplicatas)
                $existingPermission = DB::table('permissions')
                    ->where('code', $permissionData['code'])
                    ->where('institution_id', $permissionData['institution_id'])
                    ->first();

                if (!$existingPermission) {
                    $permissionId = DB::table('permissions')->insertGetId($permissionData);
                    $this->permissionIds[$permissionData['code']] = $permissionId;
                    $createdCount++;

                    $this->command->info("✅ Permissão criada: {$permissionData['name']} (ID: {$permissionId})");
                } else {
                    $this->permissionIds[$permissionData['code']] = $existingPermission->id;
                    $this->command->info("⚠️  Permissão já existe: {$permissionData['name']} (ID: {$existingPermission->id})");
                }
            } catch (Exception $e) {
                $this->command->error("❌ Erro ao criar permissão {$permissionData['name']}: " . $e->getMessage());
            }
        }

        $this->command->info("📊 Total de permissões criadas: {$createdCount}");
        $this->command->info("📊 Total de permissões disponíveis: " . count($this->permissionIds));
    }

    /**
     * Seed de perfis de usuários
     */
    private function seedProfiles()
    {
        $this->command->info('Criando perfis de usuários...');

        // Verificar as colunas existentes nas tabelas de perfis
        $this->command->info('Verificando colunas das tabelas de perfis...');
        $driverProfileColumns = Schema::getColumnListing('driver_profiles');
        $managerProfileColumns = Schema::getColumnListing('manager_profiles');
        $passengerProfileColumns = Schema::getColumnListing('passenger_profiles');

        // Perfis de gerentes
        foreach ($this->managerIds as $index => $managerId) {
            $managerData = [
                'user_id' => $managerId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (in_array('status', $managerProfileColumns)) {
                $managerData['status'] = 'active';
            }
            if (in_array('hire_date', $managerProfileColumns)) {
                $managerData['hire_date'] = Carbon::now()->subYears(rand(1, 5))->subMonths(rand(0, 11));
            }
            if (in_array('position', $managerProfileColumns)) {
                $managerData['position'] = $index == 0 ? 'Coordenador de Transportes' : 'Assistente Administrativo';
            }
            if (in_array('notes', $managerProfileColumns)) {
                $managerData['notes'] = 'Perfil criado por seed';
            }

            DB::table('manager_profiles')->insert($managerData);
        }

        // Perfis de motoristas
        $licenseTypes = ['D', 'E', 'D'];

        foreach ($this->driverIds as $index => $driverId) {
            $driverData = [
                'user_id' => $driverId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (in_array('license_number', $driverProfileColumns)) {
                $driverData['license_number'] = rand(10000000000, 99999999999);
            }
            if (in_array('license_type', $driverProfileColumns)) {
                $driverData['license_type'] = $licenseTypes[$index % count($licenseTypes)];
            }
            if (in_array('license_expiry', $driverProfileColumns)) {
                $driverData['license_expiry'] = Carbon::now()->addYears(rand(1, 5));
            }
            if (in_array('status', $driverProfileColumns)) {
                $driverData['status'] = 'active';
            }
            if (in_array('hire_date', $driverProfileColumns)) {
                $driverData['hire_date'] = Carbon::now()->subYears(rand(1, 5))->subMonths(rand(0, 11));
            }
            if (in_array('employment_type', $driverProfileColumns)) {
                $driverData['employment_type'] = 'CLT';
            }
            if (in_array('notes', $driverProfileColumns)) {
                $driverData['notes'] = 'Perfil criado por seed';
            }

            DB::table('driver_profiles')->insert($driverData);
        }

        // Perfis de passageiros
        $addresses = [
            ['Rua Bem-te-vi, 120 - Bombas', -27.1478, -48.5215],
            ['Rua Beija-flor, 73 - Centro', -27.1355, -48.5135],
            ['Avenida Fragata, 456 - Zimbros', -27.1691, -48.5328],
            ['Rua dos Marinheiros, 232 - Morrinhos', -27.1522, -48.5071],
            ['Rua da Foca, 88 - Canto Grande', -27.1166, -48.4840],
        ];

        foreach ($this->passengerIds as $index => $passengerId) {
            $addressIndex = $index % count($addresses);

            $passengerData = [
                'user_id' => $passengerId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (in_array('birth_date', $passengerProfileColumns)) {
                $passengerData['birth_date'] = Carbon::now()->subYears(rand(6, 18))->subMonths(rand(0, 11));
            }
            if (in_array('guardian_name', $passengerProfileColumns)) {
                $passengerData['guardian_name'] = 'Responsável ' . User::find($passengerId)->name;
            }
            if (in_array('guardian_contact', $passengerProfileColumns)) {
                $passengerData['guardian_contact'] = '(47) 99' . rand(100, 999) . '-' . rand(1000, 9999);
            }
            if (in_array('address', $passengerProfileColumns)) {
                $passengerData['address'] = $addresses[$addressIndex][0];
            }
            if (in_array('default_address', $passengerProfileColumns)) {
                $passengerData['default_address'] = $addresses[$addressIndex][0];
            }
            if (in_array('default_latitude', $passengerProfileColumns)) {
                $passengerData['default_latitude'] = $addresses[$addressIndex][1];
            }
            if (in_array('default_longitude', $passengerProfileColumns)) {
                $passengerData['default_longitude'] = $addresses[$addressIndex][2];
            }
            if (in_array('school_grade', $passengerProfileColumns)) {
                $passengerData['school_grade'] = rand(1, 9) . 'º ano';
            }
            if (in_array('special_needs', $passengerProfileColumns)) {
                $passengerData['special_needs'] = rand(0, 10) > 8 ? 'Alergia alimentar' : null;
            }
            if (in_array('emergency_contact', $passengerProfileColumns)) {
                $passengerData['emergency_contact'] = 'Responsável ' . User::find($passengerId)->name;
            }
            if (in_array('emergency_phone', $passengerProfileColumns)) {
                $passengerData['emergency_phone'] = '(47) 99' . rand(100, 999) . '-' . rand(1000, 9999);
            }
            if (in_array('notes', $passengerProfileColumns)) {
                $passengerData['notes'] = 'Perfil criado por seed';
            }

            DB::table('passenger_profiles')->insert($passengerData);
        }
    }

    /**
     * Seed de relacionamentos entre perfis e instituições
     */
    private function seedRelationships()
    {
        $this->command->info('Criando relacionamentos...');

        // Verificar se as tabelas necessárias existem
        if (!Schema::hasTable('institution_user')) {
            $this->command->warn('Tabela institution_user não encontrada. Pulando relacionamentos.');
            return;
        }

        // 1. Vincular todos os usuários diretamente às instituições (tabela institution_user)
        // Admins vinculados à prefeitura
        foreach ($this->adminIds as $adminId) {
            DB::table('institution_user')->insert([
                'institution_id' => $this->prefectureId,
                'user_id' => $adminId,
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Gerentes vinculados à prefeitura
        foreach ($this->managerIds as $managerId) {
            DB::table('institution_user')->insert([
                'institution_id' => $this->prefectureId,
                'user_id' => $managerId,
                'role' => 'manager',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Motoristas vinculados à prefeitura
        foreach ($this->driverIds as $driverId) {
            DB::table('institution_user')->insert([
                'institution_id' => $this->prefectureId,
                'user_id' => $driverId,
                'role' => 'driver',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Passageiros vinculados às escolas
        foreach ($this->passengerIds as $index => $passengerId) {
            $schoolIndex = $index % count($this->schoolIds);
            DB::table('institution_user')->insert([
                'institution_id' => $this->schoolIds[$schoolIndex],
                'user_id' => $passengerId,
                'role' => 'student',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Continuar com o restante dos relacionamentos específicos...
        // (código dos relacionamentos específicos mantido igual)
    }

    /**
     * Seed de pontos para as rotas
     */
    /**
     * Seed de pontos para as rotas - VERSÃO INCREMENTADA
     */
    private function seedPoints()
    {
        $this->command->info('Criando pontos para as rotas...');

        if (!$this->prefectureId) {
            $this->command->warn('Prefeitura não encontrada. Pulando criação de pontos.');
            return;
        }

        // Verificar colunas disponíveis na tabela points
        $pointsColumns = Schema::getColumnListing('points');
        $this->command->info('Colunas disponíveis na tabela points: ' . implode(', ', $pointsColumns));

        // Pontos expandidos para Bombinhas com os novos tipos
        $points = [
            // === TERMINAIS ===
            [
                'name' => 'Terminal Central de Bombinhas',
                'type' => 'terminal',
                'description' => 'Terminal principal de integração - Av. Leopoldo Zarling, 500',
                'latitude' => -27.1379,
                'longitude' => -48.5147,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => true,
                'capacity' => 100,
                'operating_hours' => ['06:00', '22:00'],
                'route_codes' => ['L01', 'L02', 'L03', 'LE'],
            ],
            [
                'name' => 'Terminal Bombas',
                'type' => 'terminal',
                'description' => 'Terminal secundário - Rua da Garoupa, s/n - Bombas',
                'latitude' => -27.1467,
                'longitude' => -48.5228,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => false,
                'is_accessible' => true,
                'capacity' => 50,
                'operating_hours' => ['06:30', '19:00'],
                'route_codes' => ['L01'],
            ],

            // === PONTOS EDUCACIONAIS ===
            [
                'name' => 'Escola Municipal Edith Willard',
                'type' => 'school_stop',
                'description' => 'Parada escolar principal - Rua Pardela, 111 - Bombas',
                'latitude' => -27.1467,
                'longitude' => -48.5228,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => true,
                'capacity' => 80,
                'operating_hours' => ['06:30', '18:30'],
                'route_codes' => ['L01', 'LE'],
            ],
            [
                'name' => 'Escola Básica Municipal Manoel José',
                'type' => 'school_stop',
                'description' => 'Parada escolar - Avenida Leopoldo Zarling, 196 - Centro',
                'latitude' => -27.1380,
                'longitude' => -48.5154,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => true,
                'capacity' => 60,
                'operating_hours' => ['07:00', '18:00'],
                'route_codes' => ['L01', 'L02'],
            ],
            [
                'name' => 'Escola Municipal Pequeno Príncipe',
                'type' => 'school_stop',
                'description' => 'Parada escolar - Rua Araribá, 25 - Bombas',
                'latitude' => -27.1499,
                'longitude' => -48.5146,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => true,
                'capacity' => 45,
                'operating_hours' => ['07:00', '17:30'],
                'route_codes' => ['L01'],
            ],

            // === PONTOS DE SAÚDE ===
            [
                'name' => 'Centro de Saúde de Bombinhas',
                'type' => 'health_center',
                'description' => 'Unidade Básica de Saúde - Av. Vereador Manoel dos Santos, 520',
                'latitude' => -27.1376,
                'longitude' => -48.5157,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => false,
                'is_accessible' => true,
                'capacity' => 30,
                'operating_hours' => ['07:00', '17:00'],
                'route_codes' => ['L01', 'L02'],
            ],
            [
                'name' => 'Pronto Atendimento Bombinhas',
                'type' => 'emergency',
                'description' => 'Pronto atendimento municipal - Rua da Sereia, 88',
                'latitude' => -27.1385,
                'longitude' => -48.5165,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => true,
                'capacity' => 25,
                'operating_hours' => ['24h'],
                'route_codes' => ['L01', 'LE'],
            ],

            // === PONTOS COMERCIAIS E SERVIÇOS ===
            [
                'name' => 'Mercado Municipal',
                'type' => 'market',
                'description' => 'Mercado Público de Bombinhas - Centro',
                'latitude' => -27.1368,
                'longitude' => -48.5142,
                'has_shelter' => false,
                'has_lighting' => true,
                'has_security' => false,
                'is_accessible' => false,
                'capacity' => 20,
                'operating_hours' => ['06:00', '18:00'],
                'route_codes' => ['L01', 'L02', 'L03'],
            ],
            [
                'name' => 'Agência Bancária Centro',
                'type' => 'bank',
                'description' => 'Banco do Brasil - Av. Leopoldo Zarling',
                'latitude' => -27.1375,
                'longitude' => -48.5150,
                'has_shelter' => false,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => true,
                'capacity' => 15,
                'operating_hours' => ['10:00', '16:00'],
                'route_codes' => ['L01', 'L02'],
            ],
            [
                'name' => 'Prefeitura Municipal',
                'type' => 'government_office',
                'description' => 'Sede da Prefeitura - Av. Baleia Jubarte, 328',
                'latitude' => self::BOMBINHAS_LAT,
                'longitude' => self::BOMBINHAS_LNG,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => true,
                'capacity' => 40,
                'operating_hours' => ['08:00', '17:00'],
                'route_codes' => ['L01', 'L02', 'LA'],
            ],

            // === PONTOS RESIDENCIAIS ===
            [
                'name' => 'Conjunto Habitacional Bombas',
                'type' => 'residential_complex',
                'description' => 'Residencial Popular - Bombas',
                'latitude' => -27.1485,
                'longitude' => -48.5195,
                'has_shelter' => false,
                'has_lighting' => true,
                'has_security' => false,
                'is_accessible' => false,
                'capacity' => 25,
                'operating_hours' => ['06:00', '22:00'],
                'route_codes' => ['L01'],
            ],
            [
                'name' => 'Centro de Morrinhos',
                'type' => 'neighborhood_center',
                'description' => 'Centro do bairro Morrinhos',
                'latitude' => -27.1522,
                'longitude' => -48.5071,
                'has_shelter' => false,
                'has_lighting' => true,
                'has_security' => false,
                'is_accessible' => false,
                'capacity' => 20,
                'operating_hours' => ['06:00', '22:00'],
                'route_codes' => ['L02'],
            ],

            // === PONTOS DE LAZER E CULTURA ===
            [
                'name' => 'Parque Municipal da Galheta',
                'type' => 'park',
                'description' => 'Parque ecológico - Galheta',
                'latitude' => -27.1290,
                'longitude' => -48.4820,
                'has_shelter' => false,
                'has_lighting' => false,
                'has_security' => false,
                'is_accessible' => false,
                'capacity' => 15,
                'operating_hours' => ['08:00', '18:00'],
                'route_codes' => ['L03'],
            ],
            [
                'name' => 'Centro Cultural de Bombinhas',
                'type' => 'cultural_center',
                'description' => 'Casa da Cultura - Centro',
                'latitude' => -27.1372,
                'longitude' => -48.5145,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => false,
                'is_accessible' => true,
                'capacity' => 30,
                'operating_hours' => ['08:00', '17:00'],
                'route_codes' => ['L01', 'L02'],
            ],
            [
                'name' => 'Biblioteca Municipal',
                'type' => 'library',
                'description' => 'Biblioteca Pública Municipal',
                'latitude' => -27.1377,
                'longitude' => -48.5148,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => false,
                'is_accessible' => true,
                'capacity' => 20,
                'operating_hours' => ['08:00', '17:00'],
                'route_codes' => ['L01'],
            ],
            [
                'name' => 'Ginásio Municipal',
                'type' => 'sports_center',
                'description' => 'Ginásio de Esportes - Centro',
                'latitude' => -27.1390,
                'longitude' => -48.5160,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => true,
                'capacity' => 35,
                'operating_hours' => ['06:00', '22:00'],
                'route_codes' => ['L01', 'L02'],
            ],

            // === PARADAS REGULARES ===
            [
                'name' => 'Parada Praça Central',
                'type' => 'stop',
                'description' => 'Parada central próxima à praça principal',
                'latitude' => -27.1364,
                'longitude' => -48.5147,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => false,
                'is_accessible' => true,
                'capacity' => 30,
                'operating_hours' => ['06:00', '22:00'],
                'route_codes' => ['L01', 'L02', 'L03'],
            ],
            [
                'name' => 'Parada Praia de Bombas',
                'type' => 'stop',
                'description' => 'Parada próxima ao pier da Praia de Bombas',
                'latitude' => -27.1450,
                'longitude' => -48.5200,
                'has_shelter' => false,
                'has_lighting' => true,
                'has_security' => false,
                'is_accessible' => false,
                'capacity' => 25,
                'operating_hours' => ['06:00', '20:00'],
                'route_codes' => ['L01'],
            ],
            [
                'name' => 'Parada Quatro Ilhas',
                'type' => 'stop',
                'description' => 'Parada na Praia de Quatro Ilhas',
                'latitude' => -27.1580,
                'longitude' => -48.5280,
                'has_shelter' => false,
                'has_lighting' => false,
                'has_security' => false,
                'is_accessible' => false,
                'capacity' => 15,
                'operating_hours' => ['07:00', '18:00'],
                'route_codes' => ['L02'],
            ],
            [
                'name' => 'Parada Zimbros',
                'type' => 'stop',
                'description' => 'Parada na Praia de Zimbros',
                'latitude' => -27.1691,
                'longitude' => -48.5328,
                'has_shelter' => false,
                'has_lighting' => true,
                'has_security' => false,
                'is_accessible' => false,
                'capacity' => 20,
                'operating_hours' => ['07:00', '18:00'],
                'route_codes' => ['L02'],
            ],
            [
                'name' => 'Parada Mariscal',
                'type' => 'stop',
                'description' => 'Parada na Praia do Mariscal',
                'latitude' => -27.1200,
                'longitude' => -48.4950,
                'has_shelter' => false,
                'has_lighting' => false,
                'has_security' => false,
                'is_accessible' => false,
                'capacity' => 15,
                'operating_hours' => ['07:00', '18:00'],
                'route_codes' => ['L03'],
            ],
            [
                'name' => 'Parada Canto Grande',
                'type' => 'stop',
                'description' => 'Parada no Canto Grande',
                'latitude' => -27.1166,
                'longitude' => -48.4840,
                'has_shelter' => false,
                'has_lighting' => false,
                'has_security' => false,
                'is_accessible' => false,
                'capacity' => 12,
                'operating_hours' => ['07:00', '18:00'],
                'route_codes' => ['L03'],
            ],

            // === PONTOS ESPECIAIS ===
            [
                'name' => 'Parada Expressa Centro',
                'type' => 'express_stop',
                'description' => 'Parada expressa para linhas rápidas',
                'latitude' => -27.1370,
                'longitude' => -48.5145,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => true,
                'capacity' => 50,
                'operating_hours' => ['06:00', '22:00'],
                'route_codes' => ['LE'],
            ],
            [
                'name' => 'Parada Acessível Hospital',
                'type' => 'accessible_stop',
                'description' => 'Parada totalmente acessível próxima ao hospital',
                'latitude' => -27.1385,
                'longitude' => -48.5170,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => true,
                'capacity' => 20,
                'operating_hours' => ['24h'],
                'route_codes' => ['LE', 'L01'],
            ],
            [
                'name' => 'Parada Sob Demanda Canto Grande',
                'type' => 'request_stop',
                'description' => 'Parada ativada sob demanda - final da linha',
                'latitude' => -27.1180,
                'longitude' => -48.4820,
                'has_shelter' => false,
                'has_lighting' => false,
                'has_security' => false,
                'is_accessible' => false,
                'capacity' => 8,
                'operating_hours' => ['07:00', '17:00'],
                'route_codes' => ['L03'],
            ],

            // === PONTOS DE INTEGRAÇÃO ===
            [
                'name' => 'Conexão Rodoviária',
                'type' => 'connection',
                'description' => 'Ponto de conexão com transporte intermunicipal',
                'latitude' => -27.1395,
                'longitude' => -48.5140,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => true,
                'capacity' => 60,
                'operating_hours' => ['05:00', '23:00'],
                'route_codes' => ['L01', 'L02', 'L03'],
            ],

            // === PONTOS DE REFERÊNCIA ===
            [
                'name' => 'Marco da Cidade',
                'type' => 'landmark',
                'description' => 'Marco turístico de Bombinhas',
                'latitude' => -27.1360,
                'longitude' => -48.5140,
                'has_shelter' => false,
                'has_lighting' => true,
                'has_security' => false,
                'is_accessible' => false,
                'capacity' => 10,
                'operating_hours' => ['24h'],
                'route_codes' => ['L01', 'L02'],
            ],
        ];

        $createdCount = 0;
        foreach ($points as $pointData) {
            try {
                // Dados básicos obrigatórios
                $insertData = [
                    'name' => $pointData['name'],
                    'type' => $pointData['type'],
                    'description' => $pointData['description'],
                    'latitude' => $pointData['latitude'],
                    'longitude' => $pointData['longitude'],
                    'institution_id' => $this->prefectureId,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Adicionar campos opcionais se existirem na tabela
                $optionalFields = [
                    'has_shelter', 'has_lighting', 'has_security', 'is_accessible',
                    'capacity', 'operating_hours', 'route_codes'
                ];

                foreach ($optionalFields as $field) {
                    if (in_array($field, $pointsColumns) && isset($pointData[$field])) {
                        if (in_array($field, ['operating_hours', 'route_codes'])) {
                            $insertData[$field] = json_encode($pointData[$field]);
                        } else {
                            $insertData[$field] = $pointData[$field];
                        }
                    }
                }

                // Verificar se o ponto já existe
                $existingPoint = DB::table('points')
                    ->where('name', $pointData['name'])
                    ->where('institution_id', $this->prefectureId)
                    ->first();

                if (!$existingPoint) {
                    $pointId = DB::table('points')->insertGetId($insertData);
                    $createdCount++;
                    $this->command->info("✅ Ponto criado: {$pointData['name']} (Tipo: {$pointData['type']}, ID: {$pointId})");
                } else {
                    $this->command->info("⚠️  Ponto já existe: {$pointData['name']} (ID: {$existingPoint->id})");
                }
            } catch (Exception $e) {
                $this->command->error("❌ Erro ao criar ponto {$pointData['name']}: " . $e->getMessage());
            }
        }

        $this->command->info("📊 Total de pontos criados: {$createdCount}");
        $this->command->info("📊 Tipos de pontos incluídos:");

        // Resumo por tipo
        $typeCount = [];
        foreach ($points as $point) {
            $type = $point['type'];
            $typeCount[$type] = ($typeCount[$type] ?? 0) + 1;
        }

        foreach ($typeCount as $type => $count) {
            $this->command->info("   - {$type}: {$count} pontos");
        }
    }

    /**
     * Seed de rotas com sistema de permissões - VERSÃO INCREMENTADA
     */
    /**
     * Seed de rotas usando referência para a tabela points - VERSÃO ATUALIZADA
     */
    private function seedRoutes()
    {
        $this->command->info('Criando rotas...');

        if (!$this->prefectureId) {
            $this->command->warn('Prefeitura não encontrada. Pulando criação de rotas.');
            return;
        }

        // Verificar as colunas disponíveis
        $routesColumns = Schema::getColumnListing('routes');
        $routePointsColumns = Schema::getColumnListing('route_points');

        $this->command->info('Colunas route_points: ' . implode(', ', $routePointsColumns));

        // Buscar pontos criados para referenciar pelos IDs
        $existingPoints = DB::table('points')
            ->where('institution_id', $this->prefectureId)
            ->get()
            ->keyBy('name'); // Indexar por nome para busca fácil

        if ($existingPoints->isEmpty()) {
            $this->command->warn('Nenhum ponto encontrado para criar rotas. Execute primeiro seedPoints().');
            return;
        }

        $this->command->info('Pontos disponíveis: ' . $existingPoints->keys()->implode(', '));

        // Rotas para Bombinhas usando IDs dos pontos
        $routesData = [
            [
                'name' => 'Linha 01 - Centro-Bombas Escolar',
                'description' => 'Rota escolar principal conectando Centro e Praia de Bombas',
                'schedule_type' => 'daily',
                'schedule_data' => [
                    'start_time' => '06:30',
                    'end_time' => '18:30',
                    'interval_minutes' => 30,
                    'days' => [1, 2, 3, 4, 5] // Segunda a sexta
                ],
                'is_public' => true,
                'is_published' => true,
                'points' => [
                    [
                        'point_name' => 'Terminal Central de Bombinhas',
                        'sequence' => 0,
                        'type' => 'start',
                        'stop_duration' => 5,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Parada Praça Central',
                        'sequence' => 1,
                        'type' => 'intermediate',
                        'stop_duration' => 2,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Escola Básica Municipal Manoel José',
                        'sequence' => 2,
                        'type' => 'intermediate',
                        'stop_duration' => 3,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Parada Praia de Bombas',
                        'sequence' => 3,
                        'type' => 'intermediate',
                        'stop_duration' => 2,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Escola Municipal Edith Willard',
                        'sequence' => 4,
                        'type' => 'intermediate',
                        'stop_duration' => 3,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Terminal Central de Bombinhas',
                        'sequence' => 5,
                        'type' => 'end',
                        'stop_duration' => 0,
                        'is_optional' => false
                    ],
                ],
                'permissions' => ['ESTUDANTE_REG', 'ESC_EDITH', 'ESC_MANOEL']
            ],
            [
                'name' => 'Linha 02 - Centro-Zimbros via Morrinhos',
                'description' => 'Rota turística e residencial para Zimbros',
                'schedule_type' => 'daily',
                'schedule_data' => [
                    'start_time' => '07:00',
                    'end_time' => '17:00',
                    'interval_minutes' => 45,
                    'days' => [1, 2, 3, 4, 5, 6] // Segunda a sábado
                ],
                'is_public' => true,
                'is_published' => true,
                'points' => [
                    [
                        'point_name' => 'Terminal Central de Bombinhas',
                        'sequence' => 0,
                        'type' => 'start',
                        'stop_duration' => 5,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Centro de Morrinhos',
                        'sequence' => 1,
                        'type' => 'intermediate',
                        'stop_duration' => 3,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Parada Quatro Ilhas',
                        'sequence' => 2,
                        'type' => 'intermediate',
                        'stop_duration' => 2,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Parada Zimbros',
                        'sequence' => 3,
                        'type' => 'intermediate',
                        'stop_duration' => 5,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Terminal Central de Bombinhas',
                        'sequence' => 4,
                        'type' => 'end',
                        'stop_duration' => 0,
                        'is_optional' => false
                    ],
                ],
                'permissions' => ['TRANSP_ESCOLAR', 'ESTUDANTE_REG']
            ],
            [
                'name' => 'Linha 03 - Centro-Canto Grande Expressa',
                'description' => 'Rota expressa para Canto Grande via Mariscal',
                'schedule_type' => 'daily',
                'schedule_data' => [
                    'start_time' => '06:45',
                    'end_time' => '18:15',
                    'interval_minutes' => 60,
                    'days' => [1, 2, 3, 4, 5]
                ],
                'is_public' => true,
                'is_published' => true,
                'points' => [
                    [
                        'point_name' => 'Terminal Central de Bombinhas',
                        'sequence' => 0,
                        'type' => 'start',
                        'stop_duration' => 3,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Conexão Rodoviária',
                        'sequence' => 1,
                        'type' => 'intermediate',
                        'stop_duration' => 2,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Parada Mariscal',
                        'sequence' => 2,
                        'type' => 'intermediate',
                        'stop_duration' => 2,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Parada Canto Grande',
                        'sequence' => 3,
                        'type' => 'intermediate',
                        'stop_duration' => 3,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Terminal Central de Bombinhas',
                        'sequence' => 4,
                        'type' => 'end',
                        'stop_duration' => 0,
                        'is_optional' => false
                    ],
                ],
                'permissions' => ['ESTUDANTE_REG', 'ROTA_PRIORIT']
            ],
            [
                'name' => 'Linha Especial - Necessidades Especiais',
                'description' => 'Rota dedicada para estudantes com necessidades especiais',
                'schedule_type' => 'custom',
                'schedule_data' => [
                    'description' => 'Horários flexíveis conforme necessidade',
                    'special_requirements' => true,
                    'accessible_vehicle_required' => true
                ],
                'is_public' => false,
                'is_published' => true,
                'points' => [
                    [
                        'point_name' => 'Terminal Central de Bombinhas',
                        'sequence' => 0,
                        'type' => 'start',
                        'stop_duration' => 5,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Centro de Saúde de Bombinhas',
                        'sequence' => 1,
                        'type' => 'intermediate',
                        'stop_duration' => 5,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Escola Municipal Edith Willard',
                        'sequence' => 2,
                        'type' => 'intermediate',
                        'stop_duration' => 5,
                        'is_optional' => false
                    ],
                    [
                        'point_name' => 'Terminal Central de Bombinhas',
                        'sequence' => 3,
                        'type' => 'end',
                        'stop_duration' => 0,
                        'is_optional' => false
                    ],
                ],
                'permissions' => ['NECESSID_ESP', 'SUPERVISOR_ESC']
            ],
        ];

        $createdRoutes = 0;
        foreach ($routesData as $routeData) {
            try {
                // Preparar dados da rota
                $routeInsertData = [
                    'name' => $routeData['name'],
                    'institution_id' => $this->prefectureId,
                    'total_distance' => 0,
                    'total_duration' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Adicionar campos opcionais se existirem na tabela
                if (in_array('description', $routesColumns)) {
                    $routeInsertData['description'] = $routeData['description'];
                }
                if (in_array('schedule_type', $routesColumns)) {
                    $routeInsertData['schedule_type'] = $routeData['schedule_type'];
                }
                if (in_array('schedule_data', $routesColumns)) {
                    $routeInsertData['schedule_data'] = json_encode($routeData['schedule_data']);
                }
                if (in_array('is_public', $routesColumns)) {
                    $routeInsertData['is_public'] = $routeData['is_public'];
                }
                if (in_array('is_published', $routesColumns)) {
                    $routeInsertData['is_published'] = $routeData['is_published'];
                }
                if (in_array('calculation_status', $routesColumns)) {
                    $routeInsertData['calculation_status'] = 'not_started';
                }

                // Verificar se a rota já existe
                $existingRoute = DB::table('routes')
                    ->where('name', $routeData['name'])
                    ->where('institution_id', $this->prefectureId)
                    ->first();

                if ($existingRoute) {
                    $this->command->info("⚠️  Rota já existe: {$routeData['name']} (ID: {$existingRoute->id})");
                    $this->routeIds[] = $existingRoute->id;
                    continue;
                }

                // Criar a rota
                $routeId = DB::table('routes')->insertGetId($routeInsertData);
                $this->routeIds[] = $routeId;
                $createdRoutes++;

                // Criar pontos da rota usando referências para a tabela points
                $pointsCreated = 0;
                foreach ($routeData['points'] as $pointData) {
                    $point = $existingPoints->get($pointData['point_name']);

                    if ($point) {
                        $routePointData = [
                            'route_id' => $routeId,
                            'point_id' => $point->id, // REFERÊNCIA PARA A TABELA POINTS
                            'sequence' => $pointData['sequence'],
                            'type' => $pointData['type'], // start, intermediate, end
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        // Adicionar campos opcionais se existirem na tabela
                        if (in_array('stop_duration', $routePointsColumns) && isset($pointData['stop_duration'])) {
                            $routePointData['stop_duration'] = $pointData['stop_duration'];
                        }
                        if (in_array('is_optional', $routePointsColumns) && isset($pointData['is_optional'])) {
                            $routePointData['is_optional'] = $pointData['is_optional'];
                        }
                        if (in_array('route_specific_notes', $routePointsColumns)) {
                            $routePointData['route_specific_notes'] = "Ponto da {$routeData['name']} - sequência {$pointData['sequence']}";
                        }

                        DB::table('route_points')->insert($routePointData);
                        $pointsCreated++;
                    } else {
                        $this->command->warn("⚠️  Ponto não encontrado para rota {$routeData['name']}: {$pointData['point_name']}");
                    }
                }

                $this->command->info("✅ Rota criada: {$routeData['name']} (ID: {$routeId}) com {$pointsCreated} pontos");

            } catch (Exception $e) {
                $this->command->error("❌ Erro ao criar rota {$routeData['name']}: " . $e->getMessage());
                $this->command->error('Stack trace: ' . $e->getTraceAsString());
            }
        }

        $this->command->info("📊 Total de rotas criadas: {$createdRoutes}");
        $this->command->info("📊 Total de IDs de rotas armazenadas: " . count($this->routeIds));
    }
    /**
     * Função adicional para criar métricas e estatísticas dos pontos
     */
    private function generatePointsStatistics()
    {
        $this->command->info('');
        $this->command->info('📈 === ESTATÍSTICAS DOS PONTOS CRIADOS ===');

        if (!Schema::hasTable('points')) {
            $this->command->warn('Tabela points não encontrada.');
            return;
        }

        $points = DB::table('points')->where('institution_id', $this->prefectureId)->get();

        if ($points->isEmpty()) {
            $this->command->warn('Nenhum ponto encontrado.');
            return;
        }

        // Estatísticas por tipo
        $typeStats = [];
        $accessibilityStats = [
            'with_shelter' => 0,
            'with_lighting' => 0,
            'with_security' => 0,
            'accessible' => 0,
            'total' => $points->count()
        ];

        foreach ($points as $point) {
            // Contagem por tipo
            $type = $point->type ?? 'unknown';
            $typeStats[$type] = ($typeStats[$type] ?? 0) + 1;

            // Estatísticas de acessibilidade
            if (isset($point->has_shelter) && $point->has_shelter) $accessibilityStats['with_shelter']++;
            if (isset($point->has_lighting) && $point->has_lighting) $accessibilityStats['with_lighting']++;
            if (isset($point->has_security) && $point->has_security) $accessibilityStats['with_security']++;
            if (isset($point->is_accessible) && $point->is_accessible) $accessibilityStats['accessible']++;
        }

        // Exibir estatísticas por tipo
        $this->command->info('📊 Distribuição por tipo de ponto:');
        foreach ($typeStats as $type => $count) {
            $percentage = round(($count / $points->count()) * 100, 1);
            $this->command->info("   - {$type}: {$count} pontos ({$percentage}%)");
        }

        // Exibir estatísticas de infraestrutura
        $this->command->info('');
        $this->command->info('🏗️  Infraestrutura disponível:');
        foreach ($accessibilityStats as $feature => $count) {
            if ($feature === 'total') continue;
            $percentage = round(($count / $accessibilityStats['total']) * 100, 1);
            $featureName = match($feature) {
                'with_shelter' => 'Com abrigo',
                'with_lighting' => 'Com iluminação',
                'with_security' => 'Com segurança',
                'accessible' => 'Acessível PcD',
                default => $feature
            };
            $this->command->info("   - {$featureName}: {$count}/{$accessibilityStats['total']} ({$percentage}%)");
        }

        // Capacidade total
        $totalCapacity = 0;
        $pointsWithCapacity = 0;
        foreach ($points as $point) {
            if (isset($point->capacity) && $point->capacity > 0) {
                $totalCapacity += $point->capacity;
                $pointsWithCapacity++;
            }
        }

        if ($pointsWithCapacity > 0) {
            $avgCapacity = round($totalCapacity / $pointsWithCapacity, 1);
            $this->command->info('');
            $this->command->info("👥 Capacidade total do sistema: {$totalCapacity} pessoas");
            $this->command->info("📊 Capacidade média por ponto: {$avgCapacity} pessoas");
            $this->command->info("📍 Pontos com capacidade definida: {$pointsWithCapacity}/{$points->count()}");
        }

        // Pontos por categoria funcional
        $functionalCategories = [
            'Transporte' => ['terminal', 'stop', 'express_stop', 'connection', 'request_stop'],
            'Educação' => ['school_stop', 'university_stop', 'campus_entrance'],
            'Saúde' => ['hospital', 'health_center', 'emergency'],
            'Serviços' => ['government_office', 'bank', 'market'],
            'Lazer/Cultura' => ['park', 'sports_center', 'cultural_center', 'museum', 'library'],
            'Residencial' => ['residential_complex', 'neighborhood_center', 'dormitory'],
            'Especiais' => ['accessible_stop', 'temporary_stop', 'landmark']
        ];

        $this->command->info('');
        $this->command->info('🏛️  Distribuição por categoria funcional:');

        foreach ($functionalCategories as $category => $types) {
            $categoryCount = 0;
            foreach ($points as $point) {
                if (in_array($point->type, $types)) {
                    $categoryCount++;
                }
            }
            if ($categoryCount > 0) {
                $percentage = round(($categoryCount / $points->count()) * 100, 1);
                $this->command->info("   - {$category}: {$categoryCount} pontos ({$percentage}%)");
            }
        }
    }

    /**
     * Seed de pontos temporários e sazonais
     */
    private function seedTemporaryAndSeasonalPoints()
    {
        $this->command->info('Criando pontos temporários e sazonais...');

        if (!Schema::hasTable('points')) {
            $this->command->warn('Tabela points não encontrada.');
            return;
        }

        $pointsColumns = Schema::getColumnListing('points');

        // Pontos temporários para alta temporada (dezembro a março)
        $temporaryPoints = [
            [
                'name' => 'Parada Temporária Reveillon',
                'type' => 'temporary_stop',
                'description' => 'Parada especial para eventos de fim de ano - Praia Central',
                'latitude' => -27.1355,
                'longitude' => -48.5130,
                'has_shelter' => false,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => false,
                'capacity' => 150,
                'operating_hours' => ['18:00', '06:00'], // Horário noturno
                'route_codes' => ['LT01', 'LE'],
                'seasonal_data' => [
                    'active_period' => 'dezembro_janeiro',
                    'event_related' => true,
                    'temporary_infrastructure' => true
                ]
            ],
            [
                'name' => 'Parada Temporária Carnaval',
                'type' => 'temporary_stop',
                'description' => 'Parada para blocos de carnaval - Av. Leopoldo Zarling',
                'latitude' => -27.1370,
                'longitude' => -48.5140,
                'has_shelter' => false,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => true,
                'capacity' => 200,
                'operating_hours' => ['14:00', '04:00'],
                'route_codes' => ['LT02'],
                'seasonal_data' => [
                    'active_period' => 'fevereiro_marco',
                    'event_related' => true,
                    'requires_special_permit' => true
                ]
            ],
            [
                'name' => 'Parada Festival de Inverno',
                'type' => 'temporary_stop',
                'description' => 'Parada para festival cultural de inverno',
                'latitude' => -27.1365,
                'longitude' => -48.5155,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => false,
                'is_accessible' => true,
                'capacity' => 80,
                'operating_hours' => ['16:00', '23:00'],
                'route_codes' => ['LT03'],
                'seasonal_data' => [
                    'active_period' => 'julho',
                    'event_related' => true,
                    'cultural_event' => true
                ]
            ],
            [
                'name' => 'Parada Temporária Praia Emergência',
                'type' => 'temporary_stop',
                'description' => 'Parada de emergência para salvamento marítimo - Praia de Bombas',
                'latitude' => -27.1445,
                'longitude' => -48.5195,
                'has_shelter' => false,
                'has_lighting' => true,
                'has_security' => false,
                'is_accessible' => true,
                'capacity' => 30,
                'operating_hours' => ['24h'],
                'route_codes' => ['LE', 'L04'],
                'seasonal_data' => [
                    'active_period' => 'dezembro_marco',
                    'emergency_related' => true,
                    'beach_patrol' => true
                ]
            ],
            [
                'name' => 'Ponto de Apoio Madrugada',
                'type' => 'temporary_stop',
                'description' => 'Ponto de apoio para trabalhadores noturnos - Centro',
                'latitude' => -27.1375,
                'longitude' => -48.5148,
                'has_shelter' => true,
                'has_lighting' => true,
                'has_security' => true,
                'is_accessible' => true,
                'capacity' => 25,
                'operating_hours' => ['22:00', '06:00'],
                'route_codes' => ['L06'],
                'seasonal_data' => [
                    'active_period' => 'year_round',
                    'night_service' => true,
                    'worker_support' => true
                ]
            ]
        ];

        $createdCount = 0;
        foreach ($temporaryPoints as $pointData) {
            try {
                // Dados básicos obrigatórios
                $insertData = [
                    'name' => $pointData['name'],
                    'type' => $pointData['type'],
                    'description' => $pointData['description'],
                    'latitude' => $pointData['latitude'],
                    'longitude' => $pointData['longitude'],
                    'institution_id' => $this->prefectureId,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Adicionar campos específicos se existirem na tabela
                $optionalFields = [
                    'has_shelter', 'has_lighting', 'has_security', 'is_accessible',
                    'capacity', 'operating_hours', 'route_codes'
                ];

                foreach ($optionalFields as $field) {
                    if (in_array($field, $pointsColumns) && isset($pointData[$field])) {
                        if (in_array($field, ['operating_hours', 'route_codes', 'seasonal_data'])) {
                            $insertData[$field] = json_encode($pointData[$field]);
                        } else {
                            $insertData[$field] = $pointData[$field];
                        }
                    }
                }

                // Adicionar dados sazonais se a coluna existir
                if (in_array('seasonal_data', $pointsColumns)) {
                    $insertData['seasonal_data'] = json_encode($pointData['seasonal_data']);
                }

                // Adicionar notas explicativas
                if (in_array('notes', $pointsColumns)) {
                    $insertData['notes'] = 'Ponto temporário/sazonal - ' . $pointData['seasonal_data']['active_period'];
                }

                // Verificar se o ponto já existe
                $existingPoint = DB::table('points')
                    ->where('name', $pointData['name'])
                    ->where('institution_id', $this->prefectureId)
                    ->first();

                if (!$existingPoint) {
                    $pointId = DB::table('points')->insertGetId($insertData);
                    $createdCount++;
                    $this->command->info("✅ Ponto temporário criado: {$pointData['name']} (ID: {$pointId})");
                } else {
                    $this->command->info("⚠️  Ponto temporário já existe: {$pointData['name']} (ID: {$existingPoint->id})");
                }
            } catch (\Exception $e) {
                $this->command->error("❌ Erro ao criar ponto temporário {$pointData['name']}: " . $e->getMessage());
            }
        }

        $this->command->info("📊 Total de pontos temporários/sazonais criados: {$createdCount}");
    }

    /**
     * Seed de permissões para usuários
     */
    private function seedUserPermissions()
    {
        $this->command->info('Associando permissões aos usuários...');

        if (!Schema::hasTable('user_permissions')) {
            $this->command->warn('Tabela user_permissions não encontrada. Pulando associação de permissões.');
            return;
        }

        // Administradores - todas as permissões
        foreach ($this->adminIds as $adminId) {
            foreach ($this->permissionIds as $permissionId) {
                DB::table('user_permissions')->insert([
                    'user_id' => $adminId,
                    'permission_id' => $permissionId,
                    'granted_at' => now(),
                    'granted_by' => $adminId, // Auto-atribuição para admin
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Gerentes - permissões administrativas e de supervisão
        $managerPermissions = [
            'GERENTE_TRANSP', 'SUPERVISOR_ESC', 'TRANSP_ESCOLAR', 'ROTA_PRIORIT'
        ];

        foreach ($this->managerIds as $managerId) {
            foreach ($managerPermissions as $permissionCode) {
                if (isset($this->permissionIds[$permissionCode])) {
                    DB::table('user_permissions')->insert([
                        'user_id' => $managerId,
                        'permission_id' => $this->permissionIds[$permissionCode],
                        'granted_at' => now(),
                        'granted_by' => $this->adminIds[0] ?? null,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Motoristas - permissões operacionais
        $driverPermissions = [
            'MOTORISTA_AUT', 'TRANSP_ESCOLAR', 'ROTA_PRIORIT'
        ];

        foreach ($this->driverIds as $driverId) {
            foreach ($driverPermissions as $permissionCode) {
                if (isset($this->permissionIds[$permissionCode])) {
                    DB::table('user_permissions')->insert([
                        'user_id' => $driverId,
                        'permission_id' => $this->permissionIds[$permissionCode],
                        'granted_at' => now(),
                        'granted_by' => $this->adminIds[0] ?? null,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Estudantes - permissões escolares
        $studentPermissions = [
            'ESTUDANTE_REG', 'TRANSP_ESCOLAR'
        ];

        // Adicionar permissões específicas por escola
        $schoolPermissions = ['ESC_EDITH', 'ESC_MANOEL', 'ESC_PEQUENO'];

        foreach ($this->passengerIds as $index => $passengerId) {
            // Permissões básicas de estudante
            foreach ($studentPermissions as $permissionCode) {
                if (isset($this->permissionIds[$permissionCode])) {
                    DB::table('user_permissions')->insert([
                        'user_id' => $passengerId,
                        'permission_id' => $this->permissionIds[$permissionCode],
                        'granted_at' => now(),
                        'granted_by' => $this->adminIds[0] ?? null,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Permissão específica da escola
            $schoolIndex = $index % count($schoolPermissions);
            $schoolPermissionCode = $schoolPermissions[$schoolIndex];

            if (isset($this->permissionIds[$schoolPermissionCode])) {
                DB::table('user_permissions')->insert([
                    'user_id' => $passengerId,
                    'permission_id' => $this->permissionIds[$schoolPermissionCode],
                    'granted_at' => now(),
                    'granted_by' => $this->adminIds[0] ?? null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Alguns estudantes com necessidades especiais
            if ($index % 5 == 0 && isset($this->permissionIds['NECESSID_ESP'])) {
                DB::table('user_permissions')->insert([
                    'user_id' => $passengerId,
                    'permission_id' => $this->permissionIds['NECESSID_ESP'],
                    'granted_at' => now(),
                    'granted_by' => $this->adminIds[0] ?? null,
                    'expires_at' => now()->addYear(), // Permissão temporal
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Permissões associadas aos usuários com sucesso!');
    }

    /**
     * Seed de permissões para rotas
     */
    private function seedRoutePermissions()
    {
        $this->command->info('Associando permissões às rotas...');

        if (!Schema::hasTable('route_permissions')) {
            $this->command->warn('Tabela route_permissions não encontrada. Pulando associação de permissões às rotas.');
            return;
        }

        // Obter rotas criadas
        $routes = DB::table('routes')->whereIn('id', $this->routeIds)->get();

        foreach ($routes as $route) {
            $routePermissions = [];

            // Mapear permissões baseado no nome da rota
            switch (true) {
                case str_contains($route->name, 'Centro-Bombas'):
                    $routePermissions = ['ESTUDANTE_REG', 'ESC_EDITH'];
                    break;

                case str_contains($route->name, 'Centro-Zimbros'):
                    $routePermissions = ['TRANSP_ESCOLAR', 'ROTA_PRIORIT'];
                    break;

                case str_contains($route->name, 'Centro-Canto Grande'):
                    $routePermissions = ['ESTUDANTE_REG'];
                    break;

                case str_contains($route->name, 'Necessidades Especiais'):
                    $routePermissions = ['NECESSID_ESP', 'SUPERVISOR_ESC'];
                    break;

                case str_contains($route->name, 'Administrativa'):
                    $routePermissions = ['GERENTE_TRANSP', 'ADMIN_GERAL'];
                    break;
            }

            // Associar permissões à rota
            foreach ($routePermissions as $permissionCode) {
                if (isset($this->permissionIds[$permissionCode])) {
                    DB::table('route_permissions')->insert([
                        'route_id' => $route->id,
                        'permission_id' => $this->permissionIds[$permissionCode],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Permissões associadas às rotas com sucesso!');
    }
}
