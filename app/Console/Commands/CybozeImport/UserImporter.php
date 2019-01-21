<?php

  namespace App\Console\Commands\CybozeImport;

  use App\Member;
  use App\User;
  use App\UserTeam;
  use Illuminate\Console\Command;
  use Illuminate\Support\Facades\DB;
  use Illuminate\Support\Facades\Hash;

  class UserImporter extends Command
  {
    // こちら側のDBでのチームID
    protected $teamId = 38;
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
      $filepath = fopen(storage_path('app/')
        . 'cybozu/user' . $this->teamId . '.csv', 'r');
      fgetcsv($filepath); //ヘッダ行
      DB::transaction(function () use ($filepath) {
        while ($row = fgetcsv($filepath)) {
          $name = $row[0] . '　' .  $row[1];
          $name_kana = $row[2] . '　' .  $row[3];
          $type = $row[5];
          $prof_img_filename = 'boy.png';
          if ($type == '2') {
            $prof_img_filename = 'man.png';
          } else if ($type == '3') {
            $prof_img_filename = strpos($name, '母')? 'woman.png' : 'man.png';
          }

          $this->info($name . ' ' . strpos($name, '母'));
          $member = Member::create([
            "team_id" => $this->teamId,
            "name" => $name,
            "name_kana" => $name_kana,
            "type" => $type,
            "birthday" => null,
            "backno" => $type == '1' && $row[4]? $row[4] : null,
            "prof_img_filename" => $prof_img_filename,
            "created_id" => 0,
            "updated_id" => 0
          ]);
          if ($type == '2' || $type == '3') { //スタッフOR家族
            $email = $row[4];
            $existingUser = User::where('email', $email)->first();
            if ($existingUser) {
              $userTeam = UserTeam::create([
                "user_id" => $existingUser->id,
                "team_id" => $this->teamId,
                "created_id" => 0,
                "updated_id" => 0
              ]);
            } else {
              $user = User::create([
                "name" => $name,
                "name_kana" => $name_kana,
                "email" => $email,
                "member_id" => $member->id,
                "password" => Hash::make($row[4]),
                //        "status" => 'invited',
                "created_id" => 0,
                "updated_id" => 0
              ]);
              $userTeam = UserTeam::create([
                "user_id" => $user->id,
                "team_id" => $this->teamId,
                "created_id" => 0,
                "updated_id" => 0
              ]);
            }
          }
        }
      });
    }
  }
