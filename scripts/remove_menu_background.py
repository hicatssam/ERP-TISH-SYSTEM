#!/usr/bin/env python3
"""Remove the image background and save a centered, transparent PNG.

Requires rembg[cpu] and Pillow. Original source is read-only.
The rembg model downloads once to the service account's cache.
"""
import argparse
from pathlib import Path

from PIL import Image, ImageOps
from rembg import new_session, remove


def make_cutout(source: Path, output: Path, model: str) -> None:
    with Image.open(source) as file:
        image = ImageOps.exif_transpose(file).convert("RGBA")
        image.thumbnail((1400, 1400), Image.Resampling.LANCZOS)

    removed = remove(image, session=new_session(model), post_process_mask=True)
    if not isinstance(removed, Image.Image):
        raise RuntimeError("Background remover did not return an image.")

    removed = removed.convert("RGBA")
    alpha = removed.getchannel("A")
    if alpha.getextrema()[0] >= 255:
        raise RuntimeError("No transparent pixels found; preserving original.")

    bounds = alpha.getbbox()
    if bounds is None:
        raise RuntimeError("Subject was not detected; preserving original.")

    # Trim transparent padding, then center the subject on a transparent
    # square canvas. This works for burgers, fries, bottles and tall drinks.
    subject = removed.crop(bounds)
    subject.thumbnail((920, 920), Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", (1024, 1024), (0, 0, 0, 0))
    canvas.alpha_composite(
        subject,
        ((1024 - subject.width) // 2, (1024 - subject.height) // 2),
    )
    output.parent.mkdir(parents=True, exist_ok=True)
    canvas.save(output, format="PNG", optimize=True)


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--model", default="u2net")
    args = parser.parse_args()
    make_cutout(args.input, args.output, args.model)
