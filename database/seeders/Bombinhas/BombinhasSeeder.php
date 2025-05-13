<?php

namespace Database\Seeders\Bombinhas;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\Users\User;
use App\Models\Institution;

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

    /**
     * Roda o seeder com dados específicos para Bombinhas - SC
     */
    public function run()
    {
        $this->command->info('Iniciando seed de dados para Bombinhas - SC');

        // Criar usuários de base
        $this->seedUsers();

        // Criar instituições para Bombinhas
        $this->seedInstitutions();

        // Criar perfis de usuários se as tabelas existirem
        if (Schema::hasTable('driver_profiles') &&
            Schema::hasTable('manager_profiles') &&
            Schema::hasTable('passenger_profiles')) {
            $this->seedProfiles();
        } else {
            $this->command->warn('Tabelas de perfis não encontradas. Pulando criação de perfis.');
        }

        // Estabelecer as relações, se as tabelas existirem
        if (Schema::hasTable('driver_institution') &&
            Schema::hasTable('manager_institution') &&
            Schema::hasTable('passenger_institution') &&
            Schema::hasTable('institution_user')) {
            $this->seedRelationships();
        } else {
            $this->command->warn('Tabelas de relacionamentos não encontradas. Pulando criação de relacionamentos.');
        }

        // Criar pontos para Bombinhas se a tabela existir
        if (Schema::hasTable('points')) {
            $this->seedPoints();
        } else {
            $this->command->warn('Tabela points não encontrada. Pulando criação de pontos.');
        }

        // Criar rotas se a tabela existir
        if (Schema::hasTable('routes') && Schema::hasTable('route_points')) {
            $this->seedRoutes();
        } else {
            $this->command->warn('Tabelas de rotas não encontradas. Pulando criação de rotas.');
        }

        $this->command->info('Dados de Bombinhas - SC criados com sucesso!');
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
            Institution::create([
                'name' => $school['name'],
                'type' => 'school',
                'city' => 'Bombinhas',
                'state' => 'SC',
                'address' => $school['address'],
                'latitude' => $school['latitude'],
                'longitude' => $school['longitude'],
                'parent_id' => $prefecture->id,
                'notes' => 'Escola Municipal em Bombinhas',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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
            Institution::create([
                'name' => $department['name'],
                'type' => 'department',
                'city' => 'Bombinhas',
                'state' => 'SC',
                'address' => $department['address'],
                'latitude' => $department['latitude'],
                'longitude' => $department['longitude'],
                'parent_id' => $prefecture->id,
                'notes' => 'Departamento da Prefeitura de Bombinhas',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
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

        // Prefeitura
        $prefecture = Institution::where('type', 'prefecture')->first();
        if (!$prefecture) {
            $this->command->warn('Prefeitura não encontrada. Pulando relacionamentos.');
            return;
        }

        // Departamentos
        $educationDept = Institution::where('name', 'LIKE', '%Secretaria de Educação%')->first();

        // Escolas
        $schools = Institution::where('type', 'school')->get();

        // 1. Vincular todos os usuários diretamente às instituições (tabela institution_user)
        // Admins vinculados à prefeitura
        foreach ($this->adminIds as $adminId) {
            DB::table('institution_user')->insert([
                'institution_id' => $prefecture->id,
                'user_id' => $adminId,
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Gerentes vinculados à prefeitura
        foreach ($this->managerIds as $managerId) {
            DB::table('institution_user')->insert([
                'institution_id' => $prefecture->id,
                'user_id' => $managerId,
                'role' => 'manager',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Motoristas vinculados à prefeitura
        foreach ($this->driverIds as $driverId) {
            DB::table('institution_user')->insert([
                'institution_id' => $prefecture->id,
                'user_id' => $driverId,
                'role' => 'driver',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Passageiros vinculados às escolas
        foreach ($this->passengerIds as $index => $passengerId) {
            $schoolIndex = $index % $schools->count();
            DB::table('institution_user')->insert([
                'institution_id' => $schools[$schoolIndex]->id,
                'user_id' => $passengerId,
                'role' => 'student',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Verificar se existem tabelas de relacionamento específicas
        if (Schema::hasTable('manager_institution')) {
            $managerProfiles = DB::table('manager_profiles')
                ->whereIn('user_id', $this->managerIds)
                ->get();

            if ($managerProfiles->count() > 0) {
                // Primeiro gerente vinculado à prefeitura
                if ($managerProfiles->count() > 0) {
                    $managerInstitutionColumns = Schema::getColumnListing('manager_institution');
                    $managerInstitutionData = [
                        'manager_profile_id' => $managerProfiles[0]->id,
                        'institution_id' => $prefecture->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (in_array('is_primary', $managerInstitutionColumns)) {
                        $managerInstitutionData['is_primary'] = true;
                    }
                    if (in_array('permissions', $managerInstitutionColumns)) {
                        $managerInstitutionData['permissions'] = json_encode(['all']);
                    }
                    if (in_array('assignment_date', $managerInstitutionColumns)) {
                        $managerInstitutionData['assignment_date'] = Carbon::now()->subYears(2);
                    }
                    if (in_array('notes', $managerInstitutionColumns)) {
                        $managerInstitutionData['notes'] = 'Relacionamento criado por seed';
                    }

                    DB::table('manager_institution')->insert($managerInstitutionData);
                }

                // Segundo gerente vinculado ao departamento de educação
                if ($educationDept && $managerProfiles->count() > 1) {
                    $managerInstitutionColumns = Schema::getColumnListing('manager_institution');
                    $managerInstitutionData = [
                        'manager_profile_id' => $managerProfiles[1]->id,
                        'institution_id' => $educationDept->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (in_array('is_primary', $managerInstitutionColumns)) {
                        $managerInstitutionData['is_primary'] = true;
                    }
                    if (in_array('permissions', $managerInstitutionColumns)) {
                        $managerInstitutionData['permissions'] = json_encode(['read', 'write', 'manage_routes']);
                    }
                    if (in_array('assignment_date', $managerInstitutionColumns)) {
                        $managerInstitutionData['assignment_date'] = Carbon::now()->subYears(1);
                    }
                    if (in_array('notes', $managerInstitutionColumns)) {
                        $managerInstitutionData['notes'] = 'Relacionamento criado por seed';
                    }

                    DB::table('manager_institution')->insert($managerInstitutionData);
                }
            }
        }

        // 3. Vincular perfis de motoristas às instituições
        if (Schema::hasTable('driver_institution')) {
            $driverProfiles = DB::table('driver_profiles')
                ->whereIn('user_id', $this->driverIds)
                ->get();

            foreach ($driverProfiles as $driver) {
                $driverInstitutionColumns = Schema::getColumnListing('driver_institution');
                $driverInstitutionData = [
                    'driver_profile_id' => $driver->id,
                    'institution_id' => $prefecture->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (in_array('start_date', $driverInstitutionColumns)) {
                    $driverInstitutionData['start_date'] = Carbon::now()->subYears(rand(1, 3));
                }
                if (in_array('status', $driverInstitutionColumns)) {
                    $driverInstitutionData['status'] = 'active';
                }
                if (in_array('contract_type', $driverInstitutionColumns)) {
                    $driverInstitutionData['contract_type'] = 'permanent';
                }
                if (in_array('schedule', $driverInstitutionColumns)) {
                    $driverInstitutionData['schedule'] = json_encode([
                        'monday' => ['06:00-12:00', '13:00-17:00'],
                        'tuesday' => ['06:00-12:00', '13:00-17:00'],
                        'wednesday' => ['06:00-12:00', '13:00-17:00'],
                        'thursday' => ['06:00-12:00', '13:00-17:00'],
                        'friday' => ['06:00-12:00', '13:00-17:00'],
                    ]);
                }
                if (in_array('notes', $driverInstitutionColumns)) {
                    $driverInstitutionData['notes'] = 'Relacionamento criado por seed';
                }

                DB::table('driver_institution')->insert($driverInstitutionData);
            }
        }

        // 4. Vincular perfis de passageiros às escolas
        if (Schema::hasTable('passenger_institution')) {
            $passengerProfiles = DB::table('passenger_profiles')
                ->whereIn('user_id', $this->passengerIds)
                ->get();

            if ($schools->count() > 0) {
                foreach ($passengerProfiles as $index => $passenger) {
                    $schoolIndex = $index % $schools->count();

                    $passengerInstitutionColumns = Schema::getColumnListing('passenger_institution');
                    $passengerInstitutionData = [
                        'passenger_profile_id' => $passenger->id,
                        'institution_id' => $schools[$schoolIndex]->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (in_array('enrollment_code', $passengerInstitutionColumns)) {
                        $passengerInstitutionData['enrollment_code'] = 'BOMBA' . rand(10000, 99999);
                    }
                    if (in_array('enrollment_date', $passengerInstitutionColumns)) {
                        $passengerInstitutionData['enrollment_date'] = Carbon::now()->subMonths(rand(1, 24));
                    }
                    if (in_array('status', $passengerInstitutionColumns)) {
                        $passengerInstitutionData['status'] = 'active';
                    }
                    if (in_array('notes', $passengerInstitutionColumns)) {
                        $passengerInstitutionData['notes'] = 'Relacionamento criado por seed';
                    }

                    DB::table('passenger_institution')->insert($passengerInstitutionData);
                }
            }
        }
    }

    /**
     * Seed de pontos para as rotas
     */
    private function seedPoints()
    {
        $this->command->info('Criando pontos para as rotas...');

        $prefecture = Institution::where('type', 'prefecture')->first();
        if (!$prefecture) {
            $this->command->warn('Prefeitura não encontrada. Pulando criação de pontos.');
            return;
        }

        // Pontos em Bombinhas
        $points = [
            // Terminais
            [
                'name' => 'Terminal Central de Bombinhas',
                'type' => 'terminal',
                'description' => 'Av. Leopoldo Zarling, 500 - Centro',
                'latitude' => -27.1379,
                'longitude' => -48.5147,
            ],
            // ... outros pontos
        ];

        foreach ($points as $pointData) {
            $pointData['institution_id'] = $prefecture->id;
            $pointData['is_active'] = true;

            // Criando ponto e definindo localização geográfica
            $point = new \App\Models\Point($pointData);
            $point->location = \Clickbar\Magellan\Data\Geometries\Point::makeGeodetic(
                $pointData['latitude'],
                $pointData['longitude']
            );
            $point->save();
        }
    }
    /**
     * Seed de rotas
     */
    private function seedRoutes()
    {
        $this->command->info('Criando rotas...');

        $prefecture = Institution::where('type', 'prefecture')->first();
        if (!$prefecture) {
            $this->command->warn('Prefeitura não encontrada. Pulando criação de rotas.');
            return;
        }

        // Verificar as colunas disponíveis nas tabelas routes e route_points
        $routesColumns = Schema::getColumnListing('routes');
        $routePointsColumns = Schema::getColumnListing('route_points');
        $this->command->info('Colunas disponíveis na tabela routes: ' . implode(', ', $routesColumns));
        $this->command->info('Colunas disponíveis na tabela route_points: ' . implode(', ', $routePointsColumns));

        $points = DB::table('points')->get();
        if ($points->isEmpty()) {
            $this->command->warn('Não há pontos cadastrados. Pulando criação de rotas.');
            return;
        }

        // Rota 1: Centro -> Bombas -> Centro
        $route1PointNames = [
            'Terminal Central de Bombinhas',
            'Parada Praça Central',
            'Parada Praia de Bombas',
            'Escola Edith Willard',
            'Terminal Central de Bombinhas'
        ];

        $route1Points = $points->filter(function($point) use ($route1PointNames) {
            return in_array($point->name, $route1PointNames);
        });

        if ($route1Points->count() >= 3) {
            $routeData = [
                'name' => 'Linha 01 - Centro-Bombas',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (in_array('description', $routesColumns)) {
                $routeData['description'] = 'Rota circular Centro-Bombas';
            }
            if (in_array('institution_id', $routesColumns)) {
                $routeData['institution_id'] = $prefecture->id;
            }
            if (in_array('is_active', $routesColumns)) {
                $routeData['is_active'] = true;
            }

            $routeId = DB::table('routes')->insertGetId($routeData);

            // Adicionar pontos na ordem correta
            $order = 0;
            foreach ($route1Points as $point) {
                $routePointData = [
                    'route_id' => $routeId,
                    'point_id' => $point->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (in_array('order', $routePointsColumns)) {
                    $routePointData['order'] = $order++;
                }
                if (in_array('waiting_time', $routePointsColumns)) {
                    $routePointData['waiting_time'] = rand(0, 2);
                }

                DB::table('route_points')->insert($routePointData);
            }
        }

        // Rota 2: Centro -> Zimbros -> Centro (se os pontos existirem)
        $route2PointNames = [
            'Terminal Central de Bombinhas',
            'Ponto Morrinhos',
            'Ponto Quatro Ilhas',
            'Parada Zimbros',
            'Terminal Central de Bombinhas'
        ];

        $route2Points = $points->filter(function($point) use ($route2PointNames) {
            return in_array($point->name, $route2PointNames);
        });

        if ($route2Points->count() >= 3) {
            $routeData = [
                'name' => 'Linha 02 - Centro-Zimbros',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (in_array('description', $routesColumns)) {
                $routeData['description'] = 'Rota circular Centro-Zimbros';
            }
            if (in_array('institution_id', $routesColumns)) {
                $routeData['institution_id'] = $prefecture->id;
            }
            if (in_array('is_active', $routesColumns)) {
                $routeData['is_active'] = true;
            }

            $routeId = DB::table('routes')->insertGetId($routeData);

            // Adicionar pontos na ordem correta
            $order = 0;
            foreach ($route2Points as $point) {
                $routePointData = [
                    'route_id' => $routeId,
                    'point_id' => $point->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (in_array('order', $routePointsColumns)) {
                    $routePointData['order'] = $order++;
                }
                if (in_array('waiting_time', $routePointsColumns)) {
                    $routePointData['waiting_time'] = rand(0, 2);
                }

                DB::table('route_points')->insert($routePointData);
            }
        }

        // Rota 3: Centro -> Canto Grande -> Centro (se os pontos existirem)
        $route3PointNames = [
            'Terminal Central de Bombinhas',
            'Parada Mariscal',
            'Parada Canto Grande',
            'Terminal Central de Bombinhas'
        ];

        $route3Points = $points->filter(function($point) use ($route3PointNames) {
            return in_array($point->name, $route3PointNames);
        });

        if ($route3Points->count() >= 3) {
            $routeData = [
                'name' => 'Linha 03 - Centro-Canto Grande',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (in_array('description', $routesColumns)) {
                $routeData['description'] = 'Rota circular Centro-Canto Grande';
            }
            if (in_array('institution_id', $routesColumns)) {
                $routeData['institution_id'] = $prefecture->id;
            }
            if (in_array('is_active', $routesColumns)) {
                $routeData['is_active'] = true;
            }

            $routeId = DB::table('routes')->insertGetId($routeData);

            // Adicionar pontos na ordem correta
            $order = 0;
            foreach ($route3Points as $point) {
                $routePointData = [
                    'route_id' => $routeId,
                    'point_id' => $point->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (in_array('order', $routePointsColumns)) {
                    $routePointData['order'] = $order++;
                }
                if (in_array('waiting_time', $routePointsColumns)) {
                    $routePointData['waiting_time'] = rand(0, 2);
                }

                DB::table('route_points')->insert($routePointData);
            }
        }
    }
}
