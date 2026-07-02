# Vietnamese administrative units dataset

Source: [thanglequoc/vietnamese-provinces-database](https://github.com/thanglequoc/vietnamese-provinces-database) (MIT)

- File: `vn_provinces.json` (from `json/vn_only_simplified_json_generated_data_vn_units.json`)
- 34 provinces / municipalities
- 3,321 wards / communes (2-tier model, no district level)

Seed:

```bash
php artisan db:seed --class=VietnamProvincesSeeder --force
```
