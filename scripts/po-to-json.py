#!/usr/bin/env python3
"""Convert .po to WordPress JED JSON format for script translations."""
import json
import re
import sys

def unescape_po(s):
    """Unescape PO string (\\n, \\", \\\\) - keep UTF-8."""
    return s.replace('\\n', '\n').replace('\\"', '"').replace('\\\\', '\\')

def parse_po(path):
    """Simple .po parser - extract msgid -> msgstr pairs."""
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    entries = {}
    for block in re.split(r'\n\n+', content):
        lines = block.split('\n')
        msgid = []
        msgstr = []
        in_msgid = False
        in_msgstr = False
        for line in lines:
            if line.startswith('msgid '):
                in_msgid = True
                in_msgstr = False
                msgid = [unescape_po(line[7:].strip().strip('"'))]
            elif line.startswith('msgstr '):
                in_msgid = False
                in_msgstr = True
                msgstr = [unescape_po(line[8:].strip().strip('"'))]
            elif line.startswith('"') and in_msgid:
                msgid.append(unescape_po(line.strip().strip('"').replace('\\n','\n')))
            elif line.startswith('"') and in_msgstr:
                msgstr.append(unescape_po(line.strip().strip('"').replace('\\n','\n')))
        if msgid and msgstr:
            k = ''.join(msgid)
            v = ''.join(msgstr)
            if k:
                entries[k] = [v]
    return entries

def main():
    po_path = 'languages/gutenblock-pro-en_US.po'
    entries = parse_po(po_path)
    
    # Only include strings that appear in JS files (we include all - WordPress will use what it needs)
    jed = {
        "domain": "gutenblock-pro",
        "locale_data": {
            "gutenblock-pro": {
                "": {
                    "domain": "gutenblock-pro",
                    "lang": "en_US",
                    "plural_forms": "nplurals=2; plural=(n != 1);"
                }
            }
        }
    }
    jed["locale_data"]["gutenblock-pro"].update({k: v for k, v in entries.items() if k})
    
    # WordPress expects {domain}-{locale}-{md5}.json where md5 = md5('build/index.js')
    out_path = 'languages/gutenblock-pro-en_US-dfbff627e6c248bcb3b61d7d06da9ca9.json'
    with open(out_path, 'w', encoding='utf-8') as f:
        json.dump(jed, f, ensure_ascii=False, indent=2)
    print(f'Wrote {out_path}')

if __name__ == '__main__':
    main()
