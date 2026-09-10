# Source Sync Status

Complete local package:

`BaToPay-1.0.0-COMPLETE.tar.gz` (artifacts)

Canonical schema: `database/schema.sql` (single file, all tables).

To fully sync this repository with local source of truth:

```bash
tar -xzf BaToPay-1.0.0-COMPLETE.tar.gz
cd BaToPay-clean   # or extracted root
git remote add origin https://github.com/BaToHub/BaToPay.git
git add .
git commit -m "release: BaToPay 1.0.0 complete source"
git push -u origin main
```

Official: @BaToHub · @BaToPay_Bot · @BaTo_Help · @DatPHP
