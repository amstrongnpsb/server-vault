<?php

namespace App\Http\Controllers;

use App\Exceptions\DatabaseConnectionException;
use App\Models\ServerDatabase;
use App\Services\DatabaseConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\Inertia;
use PDO;

class DatabaseController extends Controller
{
    public function __construct(
        private DatabaseConnectionService $connectionService
    ) {}

    public function show(ServerDatabase $serverDatabase): Response
    {
        return Inertia::render('Databases/Show', [
            'database' => $serverDatabase->load('server:id,host,name'),
        ]);
    }

    public function test(ServerDatabase $serverDatabase): JsonResponse
    {
        try {
            $pdo = $this->connectionService->connect($serverDatabase);
            $pdo->query('SELECT 1');
            return response()->json(['success' => true, 'message' => 'Connection successful']);
        } catch (DatabaseConnectionException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\PDOException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function schemas(ServerDatabase $serverDatabase): JsonResponse
    {
        try {
            $pdo = $this->connectionService->connect($serverDatabase);

            $schemas = match ($serverDatabase->type) {
                'MySQL' => $this->mysqlSchemas($pdo),
                'PostgreSQL' => $this->postgresSchemas($pdo),
                default => [],
            };

            return response()->json(['data' => $schemas]);
        } catch (DatabaseConnectionException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\PDOException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function tables(ServerDatabase $serverDatabase, Request $request): JsonResponse
    {
        try {
            $schema = $request->query('schema', 'public');
            $pdo = $this->connectionService->connect($serverDatabase);

            $tables = match ($serverDatabase->type) {
                'MySQL' => $this->mysqlTables($pdo, $schema),
                'PostgreSQL' => $this->postgresTables($pdo, $schema),
                default => [],
            };

            return response()->json(['data' => $tables]);
        } catch (DatabaseConnectionException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\PDOException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function columns(ServerDatabase $serverDatabase, Request $request): JsonResponse
    {
        $request->validate(['table' => 'required|string']);

        try {
            $table = $request->query('table');
            $schema = $request->query('schema', $serverDatabase->name);
            $pdo = $this->connectionService->connect($serverDatabase);

            $columns = match ($serverDatabase->type) {
                'MySQL' => $this->mysqlColumns($pdo, $schema, $table),
                'PostgreSQL' => $this->postgresColumns($pdo, $table),
                default => [],
            };

            return response()->json(['data' => $columns]);
        } catch (DatabaseConnectionException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\PDOException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function browse(ServerDatabase $serverDatabase, Request $request): JsonResponse
    {
        $request->validate(['table' => 'required|string']);

        try {
            $table = $request->query('table');
            $page = max(1, (int) $request->integer('page', 1));
            $perPage = min(500, max(1, (int) $request->integer('per_page', 100)));
            $offset = ($page - 1) * $perPage;

            $schema = $request->query('schema', $serverDatabase->name);
            $sortBy = $request->query('sort_by');
            $sortDir = strtolower($request->query('sort_dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $pdo = $this->connectionService->connect($serverDatabase);

            $quotedTable = $this->quoteTableName($pdo, $serverDatabase->type, $schema, $table);

            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM $quotedTable");
            $total = (int) $countStmt->fetch(PDO::FETCH_OBJ)->count;

            $sql = "SELECT * FROM $quotedTable";
            if ($sortBy) {
                $safeCol = $this->quoteIdentifier($pdo, $serverDatabase->type, $sortBy);
                $sql .= " ORDER BY $safeCol $sortDir";
            }
            $sql .= " LIMIT $perPage OFFSET $offset";

            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $columns = !empty($rows) ? array_keys($rows[0]) : [];

            return response()->json([
                'data' => $rows,
                'columns' => $columns,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ]);
        } catch (DatabaseConnectionException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\PDOException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function query(ServerDatabase $serverDatabase, Request $request): JsonResponse
    {
        $request->validate(['sql' => 'required|string']);

        try {
            $pdo = $this->connectionService->connect($serverDatabase);

            $stmt = $pdo->query($request->sql);

            // Check if the query produces a result set (SELECT, SHOW, DESCRIBE, etc.)
            $selectPattern = '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i';
            if (preg_match($selectPattern, $request->sql)) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $columns = !empty($rows) ? array_keys($rows[0]) : [];
                return response()->json([
                    'type' => 'select',
                    'data' => $rows,
                    'columns' => $columns,
                    'affected' => count($rows),
                ]);
            }

            return response()->json([
                'type' => 'modification',
                'affected' => $stmt->rowCount(),
                'message' => 'Query executed. ' . $stmt->rowCount() . ' row(s) affected.',
            ]);
        } catch (DatabaseConnectionException $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\PDOException $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    private function mysqlSchemas(PDO $pdo): array
    {
        $stmt = $pdo->query('SHOW DATABASES');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function postgresSchemas(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT LIKE 'pg_%' AND schema_name != 'information_schema' ORDER BY schema_name");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function mysqlTables(PDO $pdo, string $schema): array
    {
        $stmt = $pdo->prepare('SHOW TABLES FROM `' . str_replace('`', '``', $schema) . '`');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function postgresTables(PDO $pdo, string $schema): array
    {
        $stmt = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE' ORDER BY table_name");
        $stmt->execute([$schema]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function mysqlColumns(PDO $pdo, string $schema, string $table): array
    {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $schema) . '`.`' . str_replace('`', '``', $table) . '`');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function postgresColumns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->prepare("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position");
        $stmt->execute([$table]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function quoteIdentifier(PDO $pdo, string $type, string $identifier): string
    {
        return match ($type) {
            'MySQL' => '`' . str_replace('`', '``', $identifier) . '`',
            'PostgreSQL' => '"' . str_replace('"', '""', $identifier) . '"',
            default => $identifier,
        };
    }

    private function quoteTableName(PDO $pdo, string $type, string $schema, string $table): string
    {
        return match ($type) {
            'MySQL' => '`' . str_replace('`', '``', $schema) . '`.`' . str_replace('`', '``', $table) . '`',
            'PostgreSQL' => '"' . str_replace('"', '""', $schema) . '"."' . str_replace('"', '""', $table) . '"',
            default => $table,
        };
    }
}
