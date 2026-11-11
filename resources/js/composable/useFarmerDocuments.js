/* global fetch, FormData, setTimeout */
import { usePage } from '@inertiajs/vue3'

export function useFarmerDocuments() {
  const page = usePage()
  const csrfToken = page.props.csrf_token

  async function getPresignedUrl(file) {
    const response = await fetch('/xhr/files-presigned-url', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ filename: file.name, contentType: file.type }),
    })

    if (!response.ok) {
      throw new Error(`Failed to get presigned URL: ${response.status}`)
    }

    return response.json()
  }

  async function putToPresignedUrl(url, file) {
    const response = await fetch(url, {
      method: 'PUT',
      headers: { 'Content-Type': file.type },
      body: file,
    })

    if (!response.ok) {
      throw new Error(`File upload failed: ${response.status}`)
    }
  }

  async function uploadDocument({
    farmerId,
    file,
    documentType,
    description,
    registrationNumber,
    validity,
    complianceStatus,
  }) {
    const presigned = await getPresignedUrl(file)
    await putToPresignedUrl(presigned.url, file)

    const formData = new FormData()
    formData.append('title', file.name)
    formData.append('file_name', file.name)
    formData.append('file_key', presigned.key)
    formData.append('file_type', file.type)
    formData.append('file_size', file.size)
    formData.append('description', description || '')
    formData.append('registration_number', registrationNumber || '')
    formData.append('validity', validity || '')
    formData.append('compliance_status', complianceStatus || '')
    ;[
      { key: 'farmer', value: farmerId },
      { key: 'document', value: documentType },
    ].forEach((tag, index) => {
      formData.append(`tags[${index}][key]`, tag.key)
      formData.append(`tags[${index}][value]`, tag.value)
    })

    const response = await fetch('/documents', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
      body: formData,
    })

    if (!response.ok) {
      throw new Error(`Failed to create document: ${response.status}`)
    }

    return response.json()
  }

  async function updateDocument(
    id,
    {
      farmerCode,
      file,
      documentType,
      description,
      registrationNumber,
      validity,
      title,
      complianceStatus,
    }
  ) {
    let presigned = null

    if (file) {
      presigned = await getPresignedUrl(file)
      await putToPresignedUrl(presigned.url, file)
    }

    const formData = new FormData()
    formData.append('_method', 'PUT')
    formData.append('title', title || '')
    formData.append('description', description || '')
    formData.append('registration_number', registrationNumber || '')
    formData.append('validity', validity || '')
    formData.append('compliance_status', complianceStatus || '')
    formData.append('document_type', documentType || '')
    formData.append('farmer_code', farmerCode || '')

    if (presigned && file) {
      formData.append('file_name', file.name)
      formData.append('file_key', presigned.key)
      formData.append('file_type', file.type)
      formData.append('file_size', file.size)
    }

    const response = await fetch(`/documents/${id}`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
      body: formData,
    })

    if (!response.ok) {
      throw new Error(`Failed to update document: ${response.status}`)
    }
  }

  function viewDocument(doc) {
    if (doc?.file?.path) {
      window.open(`/${doc.file.path}`, '_blank')
    }
  }

  async function downloadDocument(doc) {
    try {
      if (!doc?.file?.path) return

      const response = await fetch(`/${doc.file.path}`)
      const blob = await response.blob()
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = doc.title || 'document'
      link.style.display = 'none'
      document.body.appendChild(link)
      link.click()
      setTimeout(() => {
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
      }, 100)
    } catch {
      viewDocument(doc)
    }
  }

  return { uploadDocument, updateDocument, downloadDocument, viewDocument }
}
