import glob
import os
from pathlib import Path

session_files = sorted(glob.glob(r'C:\xampp\tmp\sess_*'), key=os.path.getmtime, reverse=True)[:5]
for path in session_files:
    print(f'FILE: {path}')
    try:
        data = Path(path).read_bytes()
        text = data.decode('utf-8', errors='replace')
        print(text[:4000])
    except Exception as exc:
        print('ERROR reading file:', exc)
    print('\n' + '='*80 + '\n')
