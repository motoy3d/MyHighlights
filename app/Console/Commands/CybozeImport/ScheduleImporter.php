<?php

  namespace App\Console\Commands\CybozeImport;

  use Illuminate\Console\Command;

  class ScheduleImporter extends Command
  {
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
      $filepath = fopen(storage_path('app/') . 'schedule.csv', 'r');
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
        }
      });
    }
  }
