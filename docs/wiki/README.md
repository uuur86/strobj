# Wiki source

These files are the source of the [GitHub wiki](https://github.com/uuur86/strobj/wiki).
Edit them here so that documentation changes are reviewed with the code, then
publish them:

```bash
git clone https://github.com/uuur86/strobj.wiki.git
cp docs/wiki/*.md strobj.wiki/
rm strobj.wiki/README.md
cd strobj.wiki && git add -A && git commit -m "Update wiki" && git push
```

Links between pages use wiki page names (for example `Getting-Started`), so they
resolve on the wiki rather than in this directory.
