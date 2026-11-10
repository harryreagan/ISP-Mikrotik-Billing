import subprocess
import sys
import zipfile
import os

VPS_HOST = "root@213.199.49.216"
LOCAL_DIR = r"c:\xampp\htdocs\poss\isp-billing"
ZIP_PATH = r"c:\xampp\htdocs\poss\isp-billing\deploy_update.zip"

EXCLUDE_DIRS = {
    '.git', 'node_modules', 'vendor', '.vscode', 'storage', 'backups'
}
EXCLUDE_FILES = {
    '.env', 'deploy_update.zip', 'deploy_clean.zip'
}

def make_zip():
    print(f"Creating deploy archive {ZIP_PATH}...")
    with zipfile.ZipFile(ZIP_PATH, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(LOCAL_DIR):
            # Prune excluded dirs
            dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
            
            for file in files:
                if file in EXCLUDE_FILES or file.endswith('.log') or file.endswith('.zip'):
                    continue
                full_path = os.path.join(root, file)
                rel_path = os.path.relpath(full_path, LOCAL_DIR)
                zipf.write(full_path, rel_path)
    size_mb = os.path.getsize(ZIP_PATH) / (1024 * 1024)
    print(f"Archive created! Size: {size_mb:.2f} MB")

def run_cmd(cmd_list):
    print("RUNNING:", " ".join(cmd_list))
    res = subprocess.run(cmd_list, capture_output=True, text=True, encoding="utf-8", errors="replace")
    if res.stdout:
        print("STDOUT:\n", res.stdout)
    if res.stderr:
        print("STDERR:\n", res.stderr)
    return res.returncode

def deploy():
    make_zip()
    
    print("\n--- 1. Uploading archive to VPS ---")
    scp_cmd = ["scp", "-o", "ConnectTimeout=15", ZIP_PATH, f"{VPS_HOST}:/tmp/deploy_update.zip"]
    code = run_cmd(scp_cmd)
    if code != 0:
        print("SCP failed!")
        return False

    print("\n--- 2. Unzipping on VPS ---")
    remote_unzip = "unzip -o /tmp/deploy_update.zip -d /var/www/poss/isp-billing && rm -f /tmp/deploy_update.zip"
    code = run_cmd(["ssh", "-o", "ConnectTimeout=15", VPS_HOST, remote_unzip])
    if code != 0:
        print("Unzip failed!")
        return False

    print("\n--- 3. Running DB Seed/Tenant-1 Check, Migrations & Clear Cache ---")
    remote_post = """
cd /var/www/poss/isp-billing && \
php artisan tinker --execute="DB::table('tenants')->updateOrInsert(['id' => 1], ['name' => 'HSmart Master Network', 'subdomain' => 'hsmart-master', 'billing_code' => 'HSMST1', 'country' => 'Kenya', 'county' => 'Nairobi', 'is_active' => 1, 'sms_balance' => 100.00, 'created_at' => now(), 'updated_at' => now()]);" && \
php artisan migrate --force && \
php artisan optimize:clear && \
php artisan test:smoke-routes
"""
    code = run_cmd(["ssh", "-o", "ConnectTimeout=20", VPS_HOST, remote_post])
    return code == 0

if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "deploy":
        success = deploy()
        sys.exit(0 if success else 1)
    elif len(sys.argv) > 1:
        cmd = " ".join(sys.argv[1:])
        code = run_cmd(["ssh", "-o", "ConnectTimeout=15", VPS_HOST, cmd])
        sys.exit(code)
    else:
        print("Usage: python deploy_helper.py deploy | <cmd>")
