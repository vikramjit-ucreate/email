<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use DB;
use Redirect;
use Carbon\Carbon;
use Config;
use Illuminate\Support\Facades\Hash;
use App\DbStats;
use App\Test;
use App\DbInfo;
//use App\Jobs\RunDatabaseBackup;

class DbStatsController extends Controller
{

	protected function swicthDatabase($db_name, $username = null,$password = null, $host = null) {

        Config::set('database.connections.temp', array(
                    'driver' => env('DB_CONNECTION'),
                    'host' => $host,
                    'port' => env('DB_PORT'),
                    'database' => $db_name,
                    'username' => $username ?? env('DB_USERNAME'),
                    'password' => $password ?? env('DB_PASSWORD'),
                    'charset' => 'utf8',
                    'prefix' => '',
                    'prefix_indexes' => true,
                    'schema' => 'public',
                    'sslmode' => 'prefer',
                ));

        return DB::connection('temp');
    }

    protected function closeTempConection() {
        Config::set('database.connections.temp', array(
                    'driver' => env('DB_CONNECTION'),
                    'host' => env('DB_HOST'),
                    'port' => env('DB_PORT'),
                    'database' => '',
                    'username' => '',
                    'password' => '',
                    'charset' => 'utf8',
                    'prefix' => '',
                    'prefix_indexes' => true,
                    'schema' => 'public',
                    'sslmode' => 'prefer',
                ));
        DB::disconnect('temp');
    }


    public function runDatabaseQueries() {
        // DB::enableQueryLog();
        $databases = Dbinfo::all();
        foreach ($databases as $key => $database) {
            $conn = $this->swicthDatabase($database->name, $database->username, $database->password, $database->host);
            DB::connection('temp')->enableQueryLog();
           
            //$conn->select($this->runInsertQueries(10, $database->id)); 
            $this->runInsertQueries(1000, $database->id); 
            $this->runSelectQueries(1000, $database->id); 
            $this->runUpdateQueries(2, $database->id); 
            $this->runDeleteQueries(2, $database->id); 
            $this->closeTempConection();

            // $this->runInsertQueries(1000);
        }    
    }


    public function runInsertQueries($query_count, $db_id) {
        $start = microtime(true);
        for ($i = 0; $i < $query_count; $i++) {
            $query = Test::on('temp')->create([
                'name' => 'parshant'.$i,
                'email' => 'parshant'.$i.'@uds.com',
                'password' => Hash::make('parshant'),
                'phone_no' => mt_rand(10000000, 99999999),
                'status' => 0,
            ]);
        }
        $end = (microtime(true));
        $query = DB::connection('temp')->getQueryLog();
        $last_query = last(array_column($query, 'query'));
        $this->saveQueryStats($db_id, $last_query, $start, $end);        
    }

    public function runSelectQueries($query_count, $db_id) {
    	$start = microtime(true);
        for ($i = 0; $i < $query_count; $i++) {
           $d = Test::on('temp')->inRandomOrder()->limit(500)->get();
        }
        $end = (microtime(true));
        $query = DB::connection('temp')->getQueryLog();
        $last_query = last(array_column($query, 'query'));
        $this->saveQueryStats($db_id, $last_query, $start, $end);
    }

    public function runUpdateQueries($query_count, $db_id) {
        $start = microtime(true);
        $raw_query = '';
        for ($i = 0; $i < $query_count; $i++) {
            Test::on('temp')->where('status', false)->limit(500)->update(['status'=>true]);    	
        }
        $end = (microtime(true));
        $query = DB::connection('temp')->getQueryLog();
        $last_query = last(array_column($query, 'query'));
        $this->saveQueryStats($db_id, $last_query, $start, $end);
    }

    public function runDeleteQueries($query_count, $db_id) {
    	$start = microtime(true);
        $raw_query = '';
        for ($i = 0; $i < $query_count; $i++) {
            Test::on('temp')->where('status', true)->limit(500)->delete();     
        }
        $end = (microtime(true));
        $query = DB::connection('temp')->getQueryLog();
        $last_query = last(array_column($query, 'query'));
        $this->saveQueryStats($db_id, $last_query, $start, $end);
    }

    public function saveQueryStats($db_id, $run_query, $start, $end) {
        DbStats::create([
            'db_id' => $db_id,
            'query' => $run_query,
            'start_time' => $start,
            'end_time' => $end,
            'time_taken' => $end - $start,
        ]);
    }
}