# Releasing

This project uses GitHub Actions to publish a release when a semantic version tag is pushed.

## Version `v0.1.0`

1. Ensure `main` is green (`composer lint`, `composer analyse`, `composer test`).
2. Create a tag:

```bash
git tag v0.1.0
```

3. Push the tag:

```bash
git push origin v0.1.0
```

4. GitHub Action `.github/workflows/release.yml` will:
   - validate/build/test the package,
   - create a GitHub Release for `v0.1.0`,
   - use `.github/release-notes/v0.1.0.md` as release body.

## Next versions

- Create a new notes file at `.github/release-notes/vX.Y.Z.md`.
- Tag and push `vX.Y.Z`.

