# Wait for MinIO to be ready
echo "Waiting for MinIO to be ready..."
until curl -s http://minio:9000/minio/health/ready; do
    sleep 2
done

# Set up MinIO client
mc alias set local http://minio:9000 "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD"

# Create bucket if it doesn't exist
mc mb --ignore-existing local/supreme-files

# Make bucket publicly readable (use built-in policy)
mc policy set download local/supreme-files || true
mc policy get local/supreme-files || true

# Restart MinIO to apply global config
mc admin service restart local || true

# Wait for MinIO to come back
echo "Waiting for MinIO to restart..."
until curl -s http://minio:9000/minio/health/ready; do
    sleep 2
done

# Show configuration status
echo "Checking MinIO users:"
mc admin user list local || true

echo "Bucket policy (supreme-files):"
mc policy get local/supreme-files || true

echo "Global CORS (api) snippet:"
mc admin config get local api

echo "MinIO initialization completed successfully"