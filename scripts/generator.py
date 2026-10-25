import secrets
import struct
from pathlib import Path as p
import argparse
import os


parser = argparse.ArgumentParser(description="Obfuscate a password.")
parser.add_argument('-p', '--password', help='The password to obfuscate')
parser.add_argument('-n', '--name', help='The identifier used to name files. Existing files are owerwritten.')
parser.add_argument('-d', '--directory', type=p, help='The directory to save files to. Default is working directory.')
args = parser.parse_args()

if not args.password: parser.error('No password to encode.')
if not args.name: parser.error('No identification name provided.')
if args.directory and not args.directory.is_dir(): parser.error('Directory does not exist or is not accessible') 
if not args.directory: args.directory = p(os.getcwd())

# extract password
credential = args.password.encode('utf-8')
credential_length = len(credential)

# generate random data
buffer_size = 4096 + secrets.randbelow(12288)
buffer = bytearray(secrets.token_bytes(buffer_size))

# generate offset
offset = secrets.randbelow(buffer_size - credential_length + 1)

# mask password
mask = secrets.token_bytes(credential_length)
credential_masked = bytes(c ^ m for c, m in zip(credential, mask))

# export files
output = args.directory / args.name
with open(f'{output}.key', 'wb') as f:
    f.write(mask)

with open(f'{output}.meta', 'wb') as f:
    data = struct.pack
    f.write(offset.to_bytes(4, byteorder='little', signed=True))
    f.write(credential_length.to_bytes(4, byteorder='little', signed=True))

with open(f'{output}.dat', 'wb') as f:
    buffer[offset : offset + credential_length] = credential_masked
    f.write(buffer)

print(f'Obfuscation files generated in {output.parent} for {output.name}.')