2026/04/08頃から4/19まで投稿・スケジュールのメール配信がされていなかった。
supervisordでphp artisan queue:work....を実行しているが、supervisordがおかしくなってしまっていたかもしれない。
sudo service supervisord restart で再起動したら解消した。

/var/log/supervisord.log　↓　2023年5月に再起動後、初めての再起動。
```
2023-05-28 08:54:16,553 CRIT Supervisor is running as root.  Privileges were not dropped because no user is specified in the config file.  If you intend to run as root, you can set user=root in the config file to avoid this message.
2023-05-28 08:54:16,561 INFO daemonizing the supervisord process
2023-05-28 08:54:16,563 INFO supervisord started with pid 2505
2023-05-28 08:54:17,565 INFO spawned: 'live_scouter_mp4_batch' with pid 2608
2023-05-28 08:54:18,567 INFO success: live_scouter_mp4_batch entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
2026-04-19 13:12:38,972 WARN received SIGTERM indicating exit request
2026-04-19 13:12:38,996 INFO waiting for live_scouter_mp4_batch to die
2026-04-19 13:12:39,149 INFO stopped: live_scouter_mp4_batch (terminated by SIGKILL)
2026-04-19 13:12:40,328 CRIT Supervisor is running as root.  Privileges were not dropped because no user is specified in the config file.  If you intend to run as root, you can set user=root in the config file to avoid this message.
2026-04-19 13:12:40,333 INFO daemonizing the supervisord process
2026-04-19 13:12:40,334 INFO supervisord started with pid 10928
2026-04-19 13:12:41,337 INFO spawned: 'live_scouter_mp4_batch' with pid 10931
2026-04-19 13:12:42,339 INFO success: live_scouter_mp4_batch entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
(END)
```

メール配信ログは以下に出力される。
/home/ec2-user/MyHighlights/storage/logs/laravel.log
