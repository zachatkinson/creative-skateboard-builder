#!/usr/bin/env bash
# Turns a review's screenshots into AVIF (D-088): bash tools/review-images.sh docs/reviews/M3
# Playwright writes PNG, and a full-page phone shot at three device pixels is over a megabyte that Git keeps for good.
# Each .png in the folder becomes an .avif at quality 50, a phone's at two device pixels (780 wide), and the .png is
# removed. Uses sips, which macOS ships; the walkthrough is taken on the Mac, not in CI.
set -euo pipefail
dir="${1:?The folder of screenshots, such as docs/reviews/M3}"
command -v sips >/dev/null || { echo "sips is not here: run this on the Mac that took the screenshots." >&2; exit 1; }
shopt -s nullglob
for png in "$dir"/*.png; do
	avif="${png%.png}.avif"
	width="$(sips -g pixelWidth "$png" | awk '/pixelWidth/ {print $2}')"
	# A phone's shot by its file's name alone, which may start with it (walkthrough/phone-…): the folder's path is not
	# read, so a folder named for a phone does not shrink a desktop's shot.
	name="$(basename "$png")"
	if [[ "$name" == phone-* || "$name" == *-phone-* ]] && [ "$width" -gt 780 ]; then
		sips --resampleWidth 780 -s format avif -s formatOptions 50 "$png" --out "$avif" >/dev/null
	else
		sips -s format avif -s formatOptions 50 "$png" --out "$avif" >/dev/null
	fi
	[ -s "$avif" ] || { echo "No image was written for $png." >&2; exit 1; }
	rm "$png"
done
du -sh "$dir"
