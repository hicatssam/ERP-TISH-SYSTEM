#!/usr/bin/env python3
"""Create small, transparent WebP copies offline; never modify source PNGs."""
import argparse
import os
import re
import tempfile
from pathlib import Path
from PIL import Image, features

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("directory", type=Path)
    args = parser.parse_args()
    if not features.check("webp"):
        raise SystemExit("Pillow has no WebP support")
    if not args.directory.is_dir():
        raise SystemExit("Cutout directory not found")
    source_bytes = result_bytes = converted = skipped = failed = 0
    for path in sorted(args.directory.glob("*.png")):
        if path.is_symlink() or not re.fullmatch(r"[a-f0-9]{40}\.png", path.name):
            continue
        temp = None
        try:
            original = path.stat()
            target = path.with_name(f"{path.stem}.v1-{int(original.st_mtime)}-{original.st_size}.webp")
            source_bytes += original.st_size
            if target.is_file() and 0 < target.stat().st_size < original.st_size and target.stat().st_mtime >= original.st_mtime:
                with Image.open(target) as check:
                    check.verify()
                    if check.format != "WEBP":
                        raise ValueError("Invalid existing derivative")
                result_bytes += target.stat().st_size
                skipped += 1
                continue
            with Image.open(path) as source:
                if source.width * source.height > 30_000_000:
                    raise ValueError("Image exceeds pixel limit")
                image = source.convert("RGBA")
            image.thumbnail((768, 768), Image.Resampling.LANCZOS)
            with tempfile.NamedTemporaryFile(dir=args.directory, prefix=".cutout-", suffix=".webp", delete=False) as handle:
                temp = Path(handle.name)
            image.save(temp, format="WEBP", quality=84, method=5, exact=True)
            with Image.open(temp) as check:
                check.load()
                if check.format != "WEBP" or check.size != image.size:
                    raise ValueError("Invalid derivative")
                if image.getchannel("A").getextrema()[0] < 255 and ("A" not in check.getbands() or check.getchannel("A").getextrema()[0] == 255):
                    raise ValueError("Transparency was lost")
            latest = path.stat()
            if latest.st_mtime_ns != original.st_mtime_ns or latest.st_size != original.st_size:
                raise ValueError("Source changed during encoding")
            if temp.stat().st_size >= original.st_size:
                result_bytes += original.st_size
                skipped += 1
                continue
            temp.chmod(0o644)
            os.replace(temp, target)
            result_bytes += target.stat().st_size
            converted += 1
        except Exception as exc:
            print(f"ERROR {path.name}: {exc}")
            failed += 1
        finally:
            if temp is not None:
                temp.unlink(missing_ok=True)
    print(f"converted={converted} reused_or_skipped={skipped} failed={failed}")
    print(f"source_bytes={source_bytes} effective_bytes={result_bytes}")
    if source_bytes and not failed:
        print(f"saved_percent={100*(1-result_bytes/source_bytes):.1f}")
    if failed or not source_bytes:
        raise SystemExit(1)

if __name__ == "__main__":
    main()
