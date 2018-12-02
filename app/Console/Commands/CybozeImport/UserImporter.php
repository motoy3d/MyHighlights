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
      $filepath = fopen(storage_path('app/') . 'cybozu/user.csv', 'r');
      fgetcsv($filepath); //ヘッダ行
      DB::transaction(function () use ($filepath) {
        while ($row = fgetcsv($filepath)) {
          $name = $row[0] . $row[1];
          $type = $row[5];
          $prof_img_filename = 'boy.png';
          if ($type == '2') {
            $prof_img_filename = 'man.png';
          } else if ($type == '3') {
            $prof_img_filename = strpos($name, '母')? 'woman.png' : 'man.png';
          }

          $this->info($name . ' ' . strpos($name, '母'));
          $member = Member::create([
            "team_id" => 100,
            "name" => $name,
            "name_kana" => $row[2] . $row[3],
            "type" => $type,
            "birthday" => null,
            "backno" => $type == '1' ? $row[4] : null,
            "prof_img_filename" => $prof_img_filename,
            "created_id" => 0,
            "updated_id" => 0
          ]);
          if ($type == '2' || $type == '3') { //スタッフOR家族
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
        }
      });
    }
  }
