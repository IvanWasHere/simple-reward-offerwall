provider should have provider_offer_schema drop down selector a map how their api responses should be saved to our offers table
we will start with ayetstudios offer map:
admin enters provider api url 
https://www.ayetstudios.com/offers/get/3142?apiKey=xxxx

this is example response for ayetstudios api:
{
  "status": "success",
  "num_offers": 3,
  "support_url": "https://support.ayet.io/offers?externalIdentifier={external_identifier}&placementId=2772",
  "offers": [
    {
      "id": 218592,
      "store_id": "com.ncsoft.universeapp",
      "landing_page": "https://play.google.com/store/apps/details?id=com.ncsoft.universeapp",
      "icon": "https://play-lh.googleusercontent.com/P4s0IwW1QSznws5r4d0rW0KzJpF0ag59vxuOVgIJWheY_LFUOt7RzuIsfJVBxdNHfA=w140",
      "name": "UNIVERSE",
      "description": "",
      "tags": {
        "tab": "apps",
        "tasks": [
          "install"
        ],
        "categories": [
          "games_racing",
          "games"
        ]
      },
      "icon_large": "",
      "video_url": "",
      "video_url_vp9": "",
      "platform": "android",
      "platforms": [
        "android"
      ],
      "devices": [
        "phone",
        "tablet"
      ],
      "category": "incent",
      "conversion_type": "cpi",
      "conversion_time": 900,
      "max_conversion_time": 17340,
      "conversion_instructions": "Install and open app - new users only",
      "conversion_instructions_short": "Install and open app - new users only",
      "conversion_instructions_long": "Install and open app - new users only",
      "countries": [
        "DE"
      ],
      "state_targeting": null,
      "payout_usd": 0.091,
      "currency_amount": 91,
      "epc": 0,
      "daily_cap": 1000000,
      "tracking_link": "https://www.ayetstudios.com/s2s/pub/218592/1595/2772/3142?external_identifier={external_identifier}",
      "impression_url": "https://www.ayetstudios.com/offers/shown/218592;92;0;e4f323bdf5d01e92d8632f647d905cc42f9bef8c/img.gif?external_identifier={external_identifier}",
      "created": "2021-02-10 13:56:38",
      "start_date": "2021-02-10 00:00:00",
      "end_date": "2026-02-10 00:00:00",
      "offer_owner": 0,
      "score": 0,
      "devices_whitelist": [],
      "devices_blacklist": [],
      "offer_complexity": "0",
      "payment_required": false,
      "kpi": {
        "arpu_d1": null,
        "arpu_d7": null,
        "arpu_d14": null,
        "arpu_d30": null,
        "arpu_d60": null,
        "arpu_d90": null,
        "iap_iaa_ratio": null
      },
      "introduction": "Install and enjoy this amazing app to earn rewards.",
      "rules_requirements": "Only new users are eligible for this reward.",
      "i18n": {
        "en": {
          "conversion_instructions_short": "Install and open app - new users only",
          "conversion_instructions_long": "Install and open app - new users only",
          "introduction": "Install and enjoy this amazing app to earn rewards.",
          "rules_requirements": "Only new users are eligible for this reward."
        },
        "de": {
          "conversion_instructions_short": "App installieren und öffnen - nur für neue Benutzer",
          "conversion_instructions_long": "App installieren und öffnen - nur für neue Benutzer",
          "introduction": "Installieren Sie diese tolle App und genießen Sie sie, um Belohnungen zu verdienen.",
          "rules_requirements": "Nur Neukunden sind für diese Prämie berechtigt."
        },
        "es": {
          "conversion_instructions_short": "Instala, abre y usa esta aplicación durante unos minutos.",
          "conversion_instructions_long": "<div><b><ol><li>Instale la aplicación</li><br><li>Abre la aplicación</li><br><li>Usa la aplicación por unos minutos</li></ol></b></div><br/><div>--<br/>Si ya instaló la aplicación anteriormente, no es elegible para una recompensa</div>",
          "introduction": "Instala y disfruta de esta increíble aplicación para ganar recompensas.",
          "rules_requirements": "Solo los nuevos usuarios son elegibles para esta recompensa."
        },
        "fr": {
          "conversion_instructions_short": "Installer, ouvrir et utiliser cette application pendant quelques minutes.",
          "conversion_instructions_long": "<div><b><ol><li>Installer l'application</li><br><li>Ouvrez l'application</li><br><li>Utilisez l'application pendant quelques minutes</li></ol></b></div><br/><div>--<br/>Si vous avez installé l'application avant, vous n'êtes pas admissible à recevoir une récompense.</div>",
          "introduction": "Installez et profitez de cette application incroyable pour gagner des récompenses.",
          "rules_requirements": "Seuls les nouveaux utilisateurs sont éligibles à cette récompense."
        },
        "pt": {
          "conversion_instructions_short": "Instale, abra e use este aplicativo por alguns minutos.",
          "conversion_instructions_long": "<div><b><ol><li>Instale o aplicativo</li><br><li>Abra o aplicativo</li><br><li>Use o aplicativo por alguns minutos</li></ol></b></div><br/><div>--<br/>Se você instalou o aplicativo antes, não é elegível para receber uma recompensa.</div>",
          "introduction": "Instale e aproveite este aplicativo incrível para ganhar recompensas.",
          "rules_requirements": "Apenas novos usuários são elegíveis para esta recompensa."
        },
        "ru": {
          "conversion_instructions_short": "Установите, откройте и используйте это приложение в течение нескольких минут.",
          "conversion_instructions_long": "<div><b><ol><li>Установите приложение</li><br><li>Откройте приложение</li><br><li>Используйте приложение в течение нескольких минут</li></ol></b></div><br/><div>--<br/>Если вы установили приложение до того, как вы не получите право на вознаграждение.</div>",
          "introduction": "Установите и наслаждайтесь этим удивительным приложением, чтобы получить награды.",
          "rules_requirements": "Только новые пользователи имеют право на эту награду."
        },
        "ja": {
          "conversion_instructions_short": "アプリをインストールして開きます - 新規ユーザーのみ",
          "conversion_instructions_long": "アプリをインストールして開きます - 新規ユーザーのみ",
          "introduction": "この素晴らしいアプリをインストールして、報酬を獲得しましょう。",
          "rules_requirements": "新規ユーザーのみがこの報酬の対象となります。"
        },
        "ko": {
          "conversion_instructions_short": "앱 설치 및 열기 - 신규 사용자만 해당",
          "conversion_instructions_long": "앱 설치 및 열기 - 신규 사용자만 해당",
          "introduction": "이 멋진 앱을 설치하고 즐기며 보상을 받으세요.",
          "rules_requirements": "신규 사용자만 이 보상을 받을 수 있습니다."
        },
        "th": {
          "conversion_instructions_short": "ติดตั้งและเปิดแอป - ผู้ใช้ใหม่เท่านั้น",
          "conversion_instructions_long": "ติดตั้งและเปิดแอป - ผู้ใช้ใหม่เท่านั้น",
          "introduction": "ติดตั้งและเพลิดเพลินกับแอปที่น่าทึ่งนี้เพื่อรับรางวัล",
          "rules_requirements": "เฉพาะผู้ใช้ใหม่เท่านั้นที่มีสิทธิ์ได้รับรางวัลนี้"
        },
        "zh": {
          "conversion_instructions_short": "安裝並打開應用程式 - 僅限新用戶",
          "conversion_instructions_long": "安裝並打開應用程式 - 僅限新用戶",
          "introduction": "安裝並享受這個精彩的應用程式以獲得獎勵。",
          "rules_requirements": "僅新用戶有資格獲得此獎勵。"
        },
        "id": {
          "conversion_instructions_short": "Instal dan buka aplikasi - khusus pengguna baru",
          "conversion_instructions_long": "Instal dan buka aplikasi - khusus pengguna baru",
          "introduction": "Instal dan nikmati aplikasi luar biasa ini untuk mendapatkan hadiah.",
          "rules_requirements": "Hanya pengguna baru yang berhak mendapatkan hadiah ini."
        },
        "it": {
          "conversion_instructions_short": "Installa e apri l'app - solo nuovi utenti",
          "conversion_instructions_long": "Installa e apri l'app - solo nuovi utenti",
          "introduction": "Installa e goditi questa fantastica app per guadagnare premi.",
          "rules_requirements": "Solo i nuovi utenti sono idonei per questo premio."
        }
      },
      "min_android_version": null,
      "min_ios_version": null,
      "max_android_version": null,
      "max_ios_version": null,
      "browser_requirements": []
    },
    {
      "id": 218819,
      "store_id": "com.contextlogic.wish",
      "landing_page": "https://play.google.com/store/apps/details?id=com.contextlogic.wish",
      "icon": "https://play-lh.googleusercontent.com/y7h3n9TyaRdm4bD-X3RyFACj-k8uV-mz730oufeh_88ejX4jrn3urzrMvo_rbBHvQw=w140",
      "name": "Wish - Shopping Made Fun",
      "description": "",
      "tags": {
        "tab": "apps",
        "tasks": [
          "install"
        ],
        "categories": [
          "games_racing",
          "games"
        ]
      },
      "icon_large": "",
      "video_url": "",
      "video_url_vp9": "",
      "platform": "android",
      "platforms": [
        "android"
      ],
      "devices": [
        "phone",
        "tablet"
      ],
      "category": "incent",
      "conversion_type": "cpi",
      "conversion_time": 900,
      "max_conversion_time": 17340,
      "conversion_instructions": "Install and open app - new users only",
      "conversion_instructions_short": "Install and open app - new users only",
      "conversion_instructions_long": "Install and open app - new users only",
      "countries": [
        "US"
      ],
      "state_targeting": [
        "CA",
        "NY"
      ],
      "payout_usd": 0.246,
      "currency_amount": 246,
      "epc": 0.03,
      "daily_cap": 1000000,
      "tracking_link": "https://www.ayetstudios.com/s2s/pub/218819/1595/2772/3142?external_identifier={external_identifier}",
      "impression_url": "https://www.ayetstudios.com/offers/shown/218819;92;0;e4f323bdf5d01e92d8632f647d905cc42f9bef8c/img.gif?external_identifier={external_identifier}",
      "created": "2021-02-12 15:11:35",
      "start_date": "2021-02-12 00:00:00",
      "end_date": "2026-02-12 00:00:00",
      "offer_owner": 0,
      "score": 0,
      "devices_whitelist": [],
      "devices_blacklist": [],
      "offer_complexity": "0",
      "payment_required": false,
      "kpi": {
        "arpu_d1": null,
        "arpu_d7": null,
        "arpu_d14": null,
        "arpu_d30": null,
        "arpu_d60": null,
        "arpu_d90": null,
        "iap_iaa_ratio": null
      },
      "introduction": "Install and enjoy this amazing app to earn rewards.",
      "rules_requirements": "Only new users are eligible for this reward.",
      "i18n": {
        "en": {
          "conversion_instructions_short": "Install and open app - new users only",
          "conversion_instructions_long": "Install and open app - new users only",
          "introduction": "Install and enjoy this amazing app to earn rewards.",
          "rules_requirements": "Only new users are eligible for this reward."
        },
        "de": {
          "conversion_instructions_short": "App installieren und öffnen - nur für neue Benutzer",
          "conversion_instructions_long": "App installieren und öffnen - nur für neue Benutzer",
          "introduction": "Installieren Sie diese tolle App und genießen Sie sie, um Belohnungen zu verdienen.",
          "rules_requirements": "Nur Neukunden sind für diese Prämie berechtigt."
        },
        "es": {
          "conversion_instructions_short": "Instala, abre y usa esta aplicación durante unos minutos.",
          "conversion_instructions_long": "<div><b><ol><li>Instale la aplicación</li><br><li>Abre la aplicación</li><br><li>Usa la aplicación por unos minutos</li></ol></b></div><br/><div>--<br/>Si ya instaló la aplicación anteriormente, no es elegible para una recompensa</div>",
          "introduction": "Instala y disfruta de esta increíble aplicación para ganar recompensas.",
          "rules_requirements": "Solo los nuevos usuarios son elegibles para esta recompensa."
        },
        "fr": {
          "conversion_instructions_short": "Installer, ouvrir et utiliser cette application pendant quelques minutes.",
          "conversion_instructions_long": "<div><b><ol><li>Installer l'application</li><br><li>Ouvrez l'application</li><br><li>Utilisez l'application pendant quelques minutes</li></ol></b></div><br/><div>--<br/>Si vous avez installé l'application avant, vous n'êtes pas admissible à recevoir une récompense.</div>",
          "introduction": "Installez et profitez de cette application incroyable pour gagner des récompenses.",
          "rules_requirements": "Seuls les nouveaux utilisateurs sont éligibles à cette récompense."
        },
        "pt": {
          "conversion_instructions_short": "Instale, abra e use este aplicativo por alguns minutos.",
          "conversion_instructions_long": "<div><b><ol><li>Instale o aplicativo</li><br><li>Abra o aplicativo</li><br><li>Use o aplicativo por alguns minutos</li></ol></b></div><br/><div>--<br/>Se você instalou o aplicativo antes, não é elegível para receber uma recompensa.</div>",
          "introduction": "Instale e aproveite este aplicativo incrível para ganhar recompensas.",
          "rules_requirements": "Apenas novos usuários são elegíveis para esta recompensa."
        },
        "ru": {
          "conversion_instructions_short": "Установите, откройте и используйте это приложение в течение нескольких минут.",
          "conversion_instructions_long": "<div><b><ol><li>Установите приложение</li><br><li>Откройте приложение</li><br><li>Используйте приложение в течение нескольких минут</li></ol></b></div><br/><div>--<br/>Если вы установили приложение до того, как вы не получите право на вознаграждение.</div>",
          "introduction": "Установите и наслаждайтесь этим удивительным приложением, чтобы получить награды.",
          "rules_requirements": "Только новые пользователи имеют право на эту награду."
        },
        "ja": {
          "conversion_instructions_short": "アプリをインストールして開きます - 新規ユーザーのみ",
          "conversion_instructions_long": "アプリをインストールして開きます - 新規ユーザーのみ",
          "introduction": "この素晴らしいアプリをインストールして、報酬を獲得しましょう。",
          "rules_requirements": "新規ユーザーのみがこの報酬の対象となります。"
        },
        "ko": {
          "conversion_instructions_short": "앱 설치 및 열기 - 신규 사용자만 해당",
          "conversion_instructions_long": "앱 설치 및 열기 - 신규 사용자만 해당",
          "introduction": "이 멋진 앱을 설치하고 즐기며 보상을 받으세요.",
          "rules_requirements": "신규 사용자만 이 보상을 받을 수 있습니다."
        },
        "th": {
          "conversion_instructions_short": "ติดตั้งและเปิดแอป - ผู้ใช้ใหม่เท่านั้น",
          "conversion_instructions_long": "ติดตั้งและเปิดแอป - ผู้ใช้ใหม่เท่านั้น",
          "introduction": "ติดตั้งและเพลิดเพลินกับแอปที่น่าทึ่งนี้เพื่อรับรางวัล",
          "rules_requirements": "เฉพาะผู้ใช้ใหม่เท่านั้นที่มีสิทธิ์ได้รับรางวัลนี้"
        },
        "zh": {
          "conversion_instructions_short": "安裝並打開應用程式 - 僅限新用戶",
          "conversion_instructions_long": "安裝並打開應用程式 - 僅限新用戶",
          "introduction": "安裝並享受這個精彩的應用程式以獲得獎勵。",
          "rules_requirements": "僅新用戶有資格獲得此獎勵。"
        },
        "id": {
          "conversion_instructions_short": "Instal dan buka aplikasi - khusus pengguna baru",
          "conversion_instructions_long": "Instal dan buka aplikasi - khusus pengguna baru",
          "introduction": "Instal dan nikmati aplikasi luar biasa ini untuk mendapatkan hadiah.",
          "rules_requirements": "Hanya pengguna baru yang berhak mendapatkan hadiah ini."
        },
        "it": {
          "conversion_instructions_short": "Installa e apri l'app - solo nuovi utenti",
          "conversion_instructions_long": "Installa e apri l'app - solo nuovi utenti",
          "introduction": "Installa e goditi questa fantastica app per guadagnare premi.",
          "rules_requirements": "Solo i nuovi utenti sono idonei per questo premio."
        }
      },
      "min_android_version": null,
      "min_ios_version": null,
      "max_android_version": null,
      "max_ios_version": null,
      "browser_requirements": [],
      "has_installation_callback": true,
      "publisher_cpi_compensation": false
    },
    {
      "id": 218820,
      "store_id": "",
      "landing_page": "https://play.google.com/store/apps/details?id=com.codigames.idle.barber.shop.empire.tycoon",
      "icon": "https://play-lh.googleusercontent.com/zQL7-oG8X5AUKOM9XdOLlySr0zt9iQMYrhbhmT9PFVp7EnHI0DcJAPB9-8qwNkS3Y7w=w140",
      "name": "Idle Barber Shop Tycoon - Business Management Game",
      "description": "",
      "tags": {
        "tab": "apps",
        "tasks": [
          "install"
        ],
        "categories": [
          "games_racing",
          "games"
        ]
      },
      "icon_large": "",
      "video_url": "",
      "video_url_vp9": "",
      "platform": "android",
      "platforms": [
        "android"
      ],
      "devices": [],
      "category": "incent",
      "conversion_type": "cpe",
      "conversion_time": 300,
      "max_conversion_time": 17340,
      "conversion_instructions": "Complete multiple tasks to get your rewards. {multiple_rewards}",
      "conversion_instructions_short": "Complete multiple tasks to get your rewards.",
      "conversion_instructions_long": "{multiple_rewards}",
      "countries": [],
      "state_targeting": null,
      "payout_usd": 0.506,
      "currency_amount": 506,
      "epc": "new",
      "daily_cap": 1000000,
      "tracking_link": "https://www.ayetstudios.com/s2s/pub/218819/1595/2772/3142?external_identifier={external_identifier}",
      "impression_url": "https://www.ayetstudios.com/offers/shown/218819;92;0;e4f323bdf5d01e92d8632f647d905cc42f9bef8c/img.gif?external_identifier={external_identifier}",
      "created": "2021-06-09 09:17:47",
      "start_date": "2021-06-09 00:00:00",
      "end_date": "2026-06-09 00:00:00",
      "offer_owner": 0,
      "score": 0,
      "devices_whitelist": [],
      "devices_blacklist": [],
      "offer_complexity": "0",
      "payment_required": false,
      "introduction": "Complete tasks in this game to earn rewards!",
      "rules_requirements": "Only new users are eligible for this reward.",
      "i18n": {
        "en": {
          "conversion_instructions_short": "Complete multiple tasks to get your rewards.",
          "conversion_instructions_long": "{multiple_rewards}",
          "introduction": "Complete tasks in this game to earn rewards!",
          "rules_requirements": "Only new users are eligible for this reward."
        },
        "de": {
          "conversion_instructions_short": "Schließe mehrere Aktionen ab, um deine Belohnungen zu erhalten.",
          "conversion_instructions_long": "Schließe mehrere Aktionen ab, um deine Belohnungen zu erhalten.",
          "introduction": "Schließe Aufgaben in diesem Spiel ab, um Belohnungen zu erhalten!",
          "rules_requirements": "Nur Neukunden sind für diese Prämie berechtigt."
        },
        "es": {
          "conversion_instructions_short": "Completa varias tareas para obtener tus recompensas.",
          "conversion_instructions_long": "Completa varias tareas para obtener tus recompensas.",
          "introduction": "¡Completa tareas en este juego para ganar recompensas!",
          "rules_requirements": "Solo los nuevos usuarios son elegibles para esta recompensa."
        },
        "fr": {
          "conversion_instructions_short": "Effectuez plusieurs tâches pour obtenir vos récompenses.",
          "conversion_instructions_long": "Effectuez plusieurs tâches pour obtenir vos récompenses.",
          "introduction": "Accomplissez des tâches dans ce jeu pour gagner des récompenses!",
          "rules_requirements": "Seuls les nouveaux utilisateurs sont éligibles à cette récompense."
        },
        "pt": {
          "conversion_instructions_short": "Conclua várias tarefas para obter suas recompensas.",
          "conversion_instructions_long": "Conclua várias tarefas para obter suas recompensas.",
          "introduction": "Complete tarefas neste jogo para ganhar recompensas!",
          "rules_requirements": "Apenas novos usuários são elegíveis para esta recompensa."
        },
        "ru": {
          "conversion_instructions_short": "Выполните несколько задач, чтобы получить награды.",
          "conversion_instructions_long": "Выполните несколько задач, чтобы получить награды.",
          "introduction": "Выполняйте задания в этой игре, чтобы получить награды!",
          "rules_requirements": "Только новые пользователи имеют право на эту награду."
        },
        "ja": {
          "conversion_instructions_short": "複数のタスクを完了して報酬を獲得してください。",
          "conversion_instructions_long": "複数のタスクを完了して報酬を獲得してください。",
          "introduction": "このゲームでタスクを完了して報酬を獲得しましょう!",
          "rules_requirements": "新規ユーザーのみがこの報酬の対象となります。"
        },
        "ko": {
          "conversion_instructions_short": "여러 작업을 완료하여 보상을 받으세요.",
          "conversion_instructions_long": "여러 작업을 완료하여 보상을 받으세요.",
          "introduction": "이 게임에서 작업을 완료하여 보상을 받으세요!",
          "rules_requirements": "신규 사용자만 이 보상을 받을 수 있습니다."
        },
        "th": {
          "conversion_instructions_short": "ทำงานหลายอย่างให้สำเร็จเพื่อรับรางวัลของคุณ",
          "conversion_instructions_long": "ทำงานหลายอย่างให้สำเร็จเพื่อรับรางวัลของคุณ",
          "introduction": "ทำภารกิจในเกมนี้เพื่อรับรางวัล!",
          "rules_requirements": "เฉพาะผู้ใช้ใหม่เท่านั้นที่มีสิทธิ์ได้รับรางวัลนี้"
        },
        "zh": {
          "conversion_instructions_short": "完成多項任務即可獲得獎勵。",
          "conversion_instructions_long": "完成多項任務即可獲得獎勵。",
          "introduction": "完成此遊戲中的任務以獲得獎勵!",
          "rules_requirements": "僅新用戶有資格獲得此獎勵。"
        },
        "id": {
          "conversion_instructions_short": "Selesaikan banyak tugas untuk mendapatkan hadiah Anda.",
          "conversion_instructions_long": "Selesaikan banyak tugas untuk mendapatkan hadiah Anda.",
          "introduction": "Selesaikan tugas dalam game ini untuk mendapatkan hadiah!",
          "rules_requirements": "Hanya pengguna baru yang berhak mendapatkan hadiah ini."
        },
        "it": {
          "conversion_instructions_short": "Completa più attività per ottenere le tue ricompense.",
          "conversion_instructions_long": "Completa più attività per ottenere le tue ricompense.",
          "introduction": "Completa le attività in questo gioco per guadagnare premi!",
          "rules_requirements": "Solo i nuovi utenti sono idonei per questo premio."
        }
      },
      "tasks": [
        {
          "name": "Install the app",
          "uuid": "dff19bdd-3667-31f1-ac97-7523455a215d",
          "event_name": "triggered_001",
          "payout": 0.056,
          "currency_amount": 56,
          "conversion_limit": 1,
          "single_conversion_per_day": true,
          "i18n": {
            "en": "Install the app",
            "de": "Installiere die App",
            "es": "Instala la aplicación",
            "fr": "Installez l'application",
            "pt": "instale o aplicativo",
            "ru": "Установите приложение",
            "ja": "アプリをインストールする",
            "ko": "앱 설치",
            "th": "ติดตั้งแอป",
            "zh": "安裝應用程式",
            "id": "Instal aplikasinya",
            "it": "Installa l'app"
          }
        },
        {
          "name": "Open your own salon",
          "uuid": "dff19bdd-3667-31f1-ac97-7523455a218a",
          "event_name": "triggered_002",
          "payout": 0.112,
          "currency_amount": 112,
          "conversion_limit": 1,
          "single_conversion_per_day": true,
          "i18n": {
            "en": "Open your own salon",
            "de": "Eröffne deinen eigenen Salon",
            "es": "Abre tu propio salón",
            "fr": "Ouvrez votre propre salon",
            "pt": "Abra seu próprio salão",
            "ru": "Откройте свой салон",
            "ja": "自分のサロンを開く",
            "ko": "나만의 살롱을 열어보세요",
            "th": "เปิดร้านทำผมของคุณเอง",
            "zh": "開設自己的沙龍",
            "id": "Buka salon Anda sendiri",
            "it": "Apri il tuo salone"
          }
        },
        {
          "name": "Earn 1m game dollars",
          "uuid": "dff19bdd-3667-31f1-ac97-7523455a219c",
          "event_name": "triggered_003",
          "payout": 0.168,
          "currency_amount": 168,
          "conversion_limit": 1,
          "single_conversion_per_day": true,
          "i18n": {
            "en": "Earn 1m game dollars",
            "de": "Verdienen Sie 1 Mio. Spieldollar",
            "es": "Gana 1 millón de dólares en juegos",
            "fr": "Gagnez 1 million de dollars de jeu",
            "pt": "Ganhe 1 milhão de dólares em jogos",
            "ru": "Заработайте 1 млн игровых долларов",
            "ja": "100万ゲームドルを獲得",
            "ko": "백만 달러의 게임 달러를 벌어보세요",
            "th": "รับหนึ่งล้านดอลลาร์เกม",
            "zh": "賺取一百萬遊戲幣",
            "id": "Hasilkan satu juta dolar permainan",
            "it": "Guadagna 1 milione di dollari di gioco"
          }
        },
        {
          "name": "Earn 10 milion game dollars",
          "uuid": "dff19bdd-3667-31f1-ac97-7523455a215b",
          "payout": 0.168,
          "currency_amount": 168,
          "conversion_limit": 1,
          "single_conversion_per_day": true,
          "i18n": {
            "en": "Earn 10 milion game dollars",
            "de": "Verdienen Sie 10 Millionen Spieldollar",
            "es": "Gana 10 millones de dólares en juegos",
            "fr": "Gagnez 10 millions de dollars de jeu",
            "pt": "Ganhe 10 milhões de dólares em jogos",
            "ru": "Заработайте 10 миллионов игровых долларов",
            "ja": "1,000万ゲームドルを稼ぐ",
            "ko": "게임달러 1000만 달러 벌기",
            "th": "รับเงินเกมสิบล้านดอลลาร์",
            "zh": "賺取一千萬遊戲幣",
            "id": "Hasilkan sepuluh juta dolar permainan",
            "it": "Guadagna 10 milioni di dollari di gioco"
          }
        }
      ],
      "min_android_version": null,
      "min_ios_version": null,
      "max_android_version": null,
      "max_ios_version": null,
      "browser_requirements": [
        {
          "browser": "chrome",
          "min_version": "138.22.33",
          "max_version": null
        },
        {
          "browser": "firefox",
          "min_version": "124",
          "max_version": null
        },
        {
          "browser": "safari",
          "min_version": "135",
          "max_version": null
        }
      ],
      "has_installation_callback": true,
      "publisher_cpi_compensation": false
    }
  ]
}

its should be a get or post request based on what is specify in provider map, for ayet we will use get request:
 $curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://www.ayetstudios.com/api2/publisher/reporting?startDate=2020-05-05&endDate=2020-05-6&apiKey=xxxx&placements[]=5",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}

providers should have callbacks related with them so admin can create one or more callbacks per provider 
callbacks have unique hash 
https://www.site-where-plugin-is-installed.com/postback/callbackHash?payout_usd={payout_usd}&placement_identifier={placement_identifier}&adslot_id={adslot_id}&sub_id={external_identifier}&your_parameter={custom_1}

parameters defined will depend on each provider_offer_schema for ayet studios these parameters are accepted 
Available Macros for Postback URLs:
Placeholder	Type	Description
{callback_type}	string	The type of this callback: conversion for paid conversions, chargeback for chargebacks.
{transaction_id}	string	Unique transaction id - use for duplicate checks. If chargeback it's prepend with r-. Shared across all callbacks (conversion + optional) for the same event.
{payout_usd}	float	The actual conversion payout in USD. If chargeback value is negative.
{currency_amount}	float	The amount of currency the user earned (taken from your offerwall currency configuration). If chargeback value is negative.
{external_identifier}	string	Offerwall API: The external_identifier parameter passed when requesting the offers; Static API: The value of the sub_id parameter appended to the original tracking link
{user_id}	int	Our internal ID for this offerwall user.
{placement_identifier}	string	The placement_identifier for which the conversion occured
{adslot_id}	int	The ID of the adslot for which the conversion occured
{sub_id}	string	The ID of the placement for which the conversion occured[PL-1...n]
{ip}	string	Converting device's IP address if known, 0.0.0.0 otherwise
{offer_id}	int	Offer ID of the converting offer
{offer_name}	string	Name / title of the converting offer
{device_uuid}	string	ayeT-Studios internal device identificator
{device_make}	string	Device manufacturer
{device_model}	string	Device model
{advertising_id}	string	Device advertising id (GAID/IDFA) if known, otherwise empty
{sha1_android_id}	string	Device sha1 hashed android id if known, otherwise empty
{sha1_imei}	string	Device sha1 hashed imei if known, otherwise empty
{is_chargeback}	int	Either 0 or 1. Indicator if the callback is a conversion (0) or a chargeback (1).
{chargeback_reason}	string	Reason why chargeback created. Only available if is_chargeback set to 1.
{chargeback_date}	string	Date of chargeback creation. Only available if is_chargeback set to 1.
{event_name}	string	For CPA & CPE campaigns, internal event name of the conversion.
{event_value}	float	The value associated with the event (non-billable - refer to "payout_usd" and "currency_amount" for publisher & user payout).
{task_name}	string	CPE campaigns only, shows individual task name (as shown to the user) for that conversion.
{task_uuid}	string	CPE campaigns only, shows the persistent task UUID for that conversion.
{currency_identifier}	string	Shows virtual currency name as set in adslot.
{currency_conversion_rate}	number	Shows currency conversion rate used to calculate user currency for the given conversion.
{callback_ts}	integer	Timestamp at which the user triggered the event/callback.
{click_date}	string	A string representing the date and time (in 'Y-m-d H:i:s' format) at which the user clicked on the offer.
{custom_1}	string	Custom parameter to pass variables to the conversion callbacks. Can be appended to Static API / Offerwall API tracking links or the web offerwall entry URL.
{custom_2}	string	Custom parameter to pass variables to the conversion callbacks. Can be appended to Static API / Offerwall API tracking links or the web offerwall entry URL.
{custom_3}	string	Custom parameter to pass variables to the conversion callbacks. Can be appended to Static API / Offerwall API tracking links or the web offerwall entry URL.
{custom_4}	string	Custom parameter to pass variables to the conversion callbacks. Can be appended to Static API / Offerwall API tracking links or the web offerwall entry URL.
{custom_5}	string	Custom parameter to pass variables to the conversion callbacks. Can be appended to Static API / Offerwall API tracking links or the web offerwall entry URL.
Installation Callbacks	installation	Fired when an INSTALLATION_TRACKED event occurs. Sent regardless of task visibility or payout.	Installation Callback URL
Optional Conversion Callbacks	optional	Fired for visible but unpaid tasks (tasks where the user sees the task but no publisher/user payout is earned). {payout_usd} and {currency_amount} are set to 0.	Optional Conversion Callback URL
IAP Callbacks	iap	Fired for in-app purchase events. {event_value} contains the IAP value.	IAP Callback URL
IAA Callbacks	iaa	Fired for in-app ad revenue events. {event_value} contains the IAA revenue value.	IAA Callback URL

when server receives postback/callback it should create record in callbacks table and if task is visible it should also create a pending reward in rewards table associated to corresponding user 

all values from callbacks table should be visible to admins 
