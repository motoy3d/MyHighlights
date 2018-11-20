<?php

  namespace App\Console\Commands\CybozeImport;

  use App\Member;
  use App\User;
  use Illuminate\Console\Command;
  use Illuminate\Support\Facades\DB;
  use Illuminate\Support\Facades\Hash;

  class UserImporter extends Command
  {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cybozu:user_import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cybozuからユーザーデータをインポート';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
      parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
      $filepath = fopen(storage_path('app/') . 'user.csv', 'r');
      fgetcsv($filepath); //ヘッダ行
      DB::transaction(function () use ($filepath) {
        while ($row = fgetcsv($filepath)) {
          $this->info($row[0] . $row[1]);
          $member = Member::create([
            "team_id" => 100,
            "name" => $row[0] . $row[1],
            "name_kana" => $row[2] . $row[3],
            "type" => 3,
            "birthday" => null,
            "backno" => 0,
            "prof_img_filename" => 'boy.png',
            "created_id" => 0,
            "updated_id" => 0
          ]);
          $user = User::create([
            "name" => $row[0] . $row[1],
            "name_kana" => $row[2] . $row[3],
            "email" => $row[4],
            "team_id" => 100,
            "member_id" => $member->id,
            "password" => Hash::make($row[4]),
            //        "status" => 'invited',
            "created_id" => 0,
            "updated_id" => 0
          ]);

        }
      });
    }
  }
