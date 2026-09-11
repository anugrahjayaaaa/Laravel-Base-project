#!/usr/bin/env python3
"""Documentation audit tooling for Hermes governance.

Usage (from repo root):
    python3 docs/scripts/doc-audit.py links      # broken internal links
    python3 docs/scripts/doc-audit.py security   # secrets in docs
    python3 docs/scripts/doc-audit.py status     # base feature frontmatter status
    python3 docs/scripts/doc-audit.py ids        # requirement IDs in custom specs
    python3 docs/scripts/doc-audit.py all        # all of the above
"""
import re
import sys
from pathlib import Path

DOC_ROOT = Path(__file__).resolve().parents[2] / "docs"
ALL_MD = [f for f in DOC_ROOT.rglob("*.md") if f.is_file()]

# files excluded from status audit (indexes, templates, policy, changelog, contributing)
STATUS_SKIP = {"README.md", "rules.md", "test-refs.md", "coding.md", "overview.md",
               "GOVERNANCE.md", "CHANGELOG.md", "CONTRIBUTING.md", "feature.md",
               "backend.md", "infrastructure.md"}


def check_links():
    issues = 0
    for f in ALL_MD:
        for label, link in re.findall(r'\[([^\]]*)\]\(([^)]+)\)', f.read_text(errors="ignore")):
            if link.startswith(("http://", "https://", "#")):
                continue
            target = link.split("#")[0]
            if not target:
                continue
            full = (f.parent / target).resolve()
            # allow empty features/ dir placeholder
            if str(full) == str(DOC_ROOT / "custom" / "features"):
                continue
            if not full.exists():
                print(f"  BROKEN: {f.relative_to(DOC_ROOT)} -> {link}")
                issues += 1
    return issues


def check_security():
    issues = 0
    secret_patterns = [
        r'(?i)password\s*=\s*["\']?[^\s"\']{4,}',
        r'(?i)api[_-]?key\s*=\s*["\']?[^\s"\']{8,}',
        r'sk-[a-zA-Z0-9]{16,}',
        r'(?<!\w)(AKIA|ASIA)[A-Z0-9]{16}(?!\w)',
        r'(?i)(aws_secret_access_key|private_key)\s*=.*[A-Za-z0-9+/=]{12,}',
    ]
    for f in ALL_MD:
        for line in f.read_text(errors="ignore").splitlines():
            for pat in secret_patterns:
                if re.search(pat, line):
                    if "<REDACTED>" in line or "<ENV" in line:
                        continue
                    print(f"  SECRET: {f.relative_to(DOC_ROOT)}")
                    issues += 1
                    break
    return issues


def check_status():
    issues = 0
    for f in ALL_MD:
        if f.parent.name == "features" and f.name not in STATUS_SKIP:
            text = f.read_text(errors="ignore")
            if not re.search(r'^status:\s+\w', text, re.MULTILINE):
                print(f"  NO status: {f.relative_to(DOC_ROOT)}")
                issues += 1
    return issues


def check_ids():
    issues = 0
    custom_dir = DOC_ROOT / "custom" / "features"
    if not custom_dir.exists():
        return 0
    for f in custom_dir.rglob("*.md"):
        if f.name == "README.md":
            continue
        text = f.read_text(errors="ignore")
        has_req = bool(re.search(r'FR-\d+|NFR-\d+|BR-\d+|AC-\d+', text))
        if not has_req:
            print(f"  NO IDs: {f.relative_to(DOC_ROOT)}")
            issues += 1
    return issues


CHECKS = {
    "links": check_links,
    "security": check_security,
    "status": check_status,
    "ids": check_ids,
}


def main():
    cmd = sys.argv[1] if len(sys.argv) > 1 else "all"
    if cmd == "all":
        total = 0
        for name, fn in CHECKS.items():
            print(f"=== {name} ===")
            total += fn()
        print(f"\nTotal issues: {total}")
        sys.exit(1 if total > 0 else 0)
    elif cmd in CHECKS:
        issues = CHECKS[cmd]()
        print(f"\n{issues} issue(s) in {cmd}")
        sys.exit(1 if issues > 0 else 0)
    else:
        print(f"Unknown check: {cmd}. Use: links|security|status|ids|all")
        sys.exit(2)


if __name__ == "__main__":
    main()
