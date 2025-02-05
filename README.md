

В репозитории представлен пример реализации "зеркала" для сущностей из amoCRM.

Существует определенный набор сущностей в CRM системе: компании, контакты, лиды

В базе воссоздана структура для хранения этих сущностей и взаимосвязей между ними, класс для взаимодействия с зеркалом и команда по синхронизации зеркала и сущностей в CRM


Консольная команда периодически выполняющаяся и синхронизирующая сущности в амо и зеркале:

[MirrorEntitiesSync.php](app%2FConsole%2FCommands%2FMirrorEntitiesSync.php)


Класс для взаимодействия с зеркалом:

[MirrorHelper.php](app%2Flib%2FMirrorHelper%2FMirrorHelper.php)


Миграции в директории database/migrations (все миграции 2024 года относятся к описываемому функционалу):

[2024_12_19_193715_create_mirror_leads_table.php](database%2Fmigrations%2F2024_12_19_193715_create_mirror_leads_table.php)

[2024_12_19_193749_create_mirror_companies_table.php](database%2Fmigrations%2F2024_12_19_193749_create_mirror_companies_table.php)

[2024_12_19_201013_create_mirror_contacts_table.php](database%2Fmigrations%2F2024_12_19_201013_create_mirror_contacts_table.php)

[2024_12_19_205125_create_mirror_leads_cfs_table.php](database%2Fmigrations%2F2024_12_19_205125_create_mirror_leads_cfs_table.php)

[2024_12_19_205136_create_mirror_contacts_cfs_table.php](database%2Fmigrations%2F2024_12_19_205136_create_mirror_contacts_cfs_table.php)

[2024_12_19_205145_create_mirror_companies_cfs_table.php](database%2Fmigrations%2F2024_12_19_205145_create_mirror_companies_cfs_table.php)

[2024_12_19_211602_create_mirror_relations_leads_contacts_table.php](database%2Fmigrations%2F2024_12_19_211602_create_mirror_relations_leads_contacts_table.php)

[2024_12_19_211620_create_mirror_relations_leads_companies_table.php](database%2Fmigrations%2F2024_12_19_211620_create_mirror_relations_leads_companies_table.php)

[2024_12_19_211637_create_mirror_relations_contacts_companies_table.php](database%2Fmigrations%2F2024_12_19_211637_create_mirror_relations_contacts_companies_table.php)

[2024_12_21_103310_create_mirror_leads_tags_table.php](database%2Fmigrations%2F2024_12_21_103310_create_mirror_leads_tags_table.php)

[2024_12_21_103322_create_mirror_contacts_tags_table.php](database%2Fmigrations%2F2024_12_21_103322_create_mirror_contacts_tags_table.php)

[2024_12_21_103333_create_mirror_companies_tags_table.php](database%2Fmigrations%2F2024_12_21_103333_create_mirror_companies_tags_table.php)

[2024_12_21_103429_create_mirror_relations_leads_tags_table.php](database%2Fmigrations%2F2024_12_21_103429_create_mirror_relations_leads_tags_table.php)

[2024_12_21_103440_create_mirror_relations_contacts_tags_table.php](database%2Fmigrations%2F2024_12_21_103440_create_mirror_relations_contacts_tags_table.php)

[2024_12_21_103448_create_mirror_relations_companies_tags_table.php](database%2Fmigrations%2F2024_12_21_103448_create_mirror_relations_companies_tags_table.php)


Модели в директории app:

[MirrorCompanies.php](app%2FMirrorCompanies.php)

[MirrorCompaniesCfs.php](app%2FMirrorCompaniesCfs.php)

[MirrorCompaniesTags.php](app%2FMirrorCompaniesTags.php)

[MirrorContacts.php](app%2FMirrorContacts.php)

[MirrorContactsCfs.php](app%2FMirrorContactsCfs.php)

[MirrorContactsTags.php](app%2FMirrorContactsTags.php)

[MirrorLeads.php](app%2FMirrorLeads.php)

[MirrorLeadsCfs.php](app%2FMirrorLeadsCfs.php)

[MirrorLeadsTags.php](app%2FMirrorLeadsTags.php)


Некоторые вспомогательные классы расположены в директориях:

[AmoHelper](app%2Flib%2FAmoHelper)

[lib](app%2Flib)






