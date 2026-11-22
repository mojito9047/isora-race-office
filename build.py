"""Build dist/pkg_isorairc.zip, the file to upload in Joomla's extension installer.

Run:  python build.py
"""
import io
import zipfile
from pathlib import Path

ROOT = Path(__file__).parent
SRC = ROOT / "src"
DIST = ROOT / "dist"


def zip_dir(folder: Path) -> bytes:
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, "w", zipfile.ZIP_DEFLATED) as archive:
        for path in sorted(folder.rglob("*")):
            if path.is_file():
                archive.write(path, path.relative_to(folder).as_posix())
    return buffer.getvalue()


def main() -> None:
    DIST.mkdir(exist_ok=True)
    target = DIST / "pkg_isorairc.zip"
    with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as package:
        package.write(SRC / "pkg_isorairc" / "pkg_isorairc.xml", "pkg_isorairc.xml")
        package.write(SRC / "pkg_isorairc" / "script.php", "script.php")
        package.writestr("packages/com_isorairc.zip", zip_dir(SRC / "com_isorairc"))
        package.writestr("packages/plg_task_isorairc.zip", zip_dir(SRC / "plg_task_isorairc"))
    print(f"Built {target} ({target.stat().st_size} bytes)")


if __name__ == "__main__":
    main()
