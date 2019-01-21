<?php

  namespace App\Console\Commands\CybozeImport;

  use App\Category;
  use App\Schedule;
  use App\User;
  use Carbon\Carbon;
  use Illuminate\Console\Command;
  use Illuminate\Support\Facades\DB;

  class ScheduleImporter extends Command
  {
    // こちら側のDBでのチームID
    protected $teamId = 38;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cybozu:schedule_import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cybozuからスケジュールデータをインポート';

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
        . 'cybozu/schedule' . $this->teamId . '.csv', 'r');
      fgetcsv($filepath); //ヘッダ行
      DB::transaction(function () use ($filepath) {
        $week = array( "日", "月", "火", "水", "木", "金", "土" );
        while ($row = fgetcsv($filepath)) {
          $shcedule_date = $row[0];
          $date = Carbon::parse($shcedule_date);
          $date2 = $date->format('n/j') . '（' . $week[$date->dayOfWeek] . '）';
          $title = str_replace($date2, '', $row[5]);
          $this->info($row[0] . ' ' . $title);
          // カテゴリ
          $category = Category::where('name', $row[4])->first();
          // 作成・更新ユーザー名からユーザーIDを取得
          $create_username = $row[7];
          $create_user = User::where(
            'name', str_replace_first(' ', '', $create_username))->first();
          $created_id = $create_user? $create_user->id : -1;  //-1は退会者

          $member = Schedule::create([
            "team_id" => $this->teamId,
            "schedule_date" => $shcedule_date,
            "title" => $title,
            "allday_flg" => 0,
            "time_from" => $row[1],
            "time_to" => $row[3],
            "category_id" => $category? $category->id : 0,
            "content" => $row[6],
            "notification_flg" => 0,
            "created_id" => $created_id,
            "updated_id" => $created_id
          ]);
        }
      });
    }
  }
