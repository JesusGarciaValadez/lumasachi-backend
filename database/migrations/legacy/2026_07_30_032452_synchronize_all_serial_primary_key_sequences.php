<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            DO $$
            DECLARE
                sequence_record record;
            BEGIN
                FOR sequence_record IN
                    SELECT
                        table_schema,
                        table_name,
                        column_name,
                        (regexp_match(
                            column_default,
                            $regex$nextval\('([^']+)'::regclass\)$regex$
                        ))[1]::regclass AS sequence_name
                    FROM information_schema.columns
                    WHERE table_schema = 'public'
                      AND column_name = 'id'
                      AND column_default LIKE 'nextval(%'
                LOOP
                    EXECUTE format(
                        'ALTER SEQUENCE %s OWNED BY %I.%I.%I',
                        sequence_record.sequence_name,
                        sequence_record.table_schema,
                        sequence_record.table_name,
                        sequence_record.column_name
                    );

                    EXECUTE format(
                        'SELECT setval(
                            %L::regclass,
                            COALESCE((SELECT MAX(%I) FROM %I.%I), 1),
                            EXISTS (SELECT 1 FROM %I.%I)
                        )',
                        sequence_record.sequence_name,
                        sequence_record.column_name,
                        sequence_record.table_schema,
                        sequence_record.table_name,
                        sequence_record.table_schema,
                        sequence_record.table_name
                    );
                END LOOP;
            END
            $$;
            SQL
        );
    }

    public function down(): void
    {
        // Imported sequence ownership and values cannot be safely restored.
    }
};
