import { serve } from 'https://deno.land/std@0.168.0/http/server.ts'
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
}

serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response('ok', { headers: corsHeaders })
  }

  try {
    const supabaseUrl = Deno.env.get('SUPABASE_URL')!
    const supabaseServiceKey = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!

    const supabase = createClient(supabaseUrl, supabaseServiceKey)

    const { gallery_id } = await req.json()

    if (!gallery_id) {
      return new Response(
        JSON.stringify({ error: 'gallery_id is required' }),
        {
          status: 400,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' }
        }
      )
    }

    // List all files in the gallery folder
    const { data: files, error: listError } = await supabase.storage
      .from('galleries')
      .list(gallery_id)

    if (listError) {
      throw listError
    }

    if (!files || files.length === 0) {
      return new Response(
        JSON.stringify({ message: 'No files to delete', deleted: 0 }),
        {
          headers: { ...corsHeaders, 'Content-Type': 'application/json' }
        }
      )
    }

    // Build array of file paths to delete
    const filePaths = files.map(file => `${gallery_id}/${file.name}`)

    // Delete all files
    const { error: deleteError } = await supabase.storage
      .from('galleries')
      .remove(filePaths)

    if (deleteError) {
      throw deleteError
    }

    return new Response(
      JSON.stringify({
        message: 'Gallery files deleted successfully',
        deleted: filePaths.length,
        paths: filePaths
      }),
      {
        headers: { ...corsHeaders, 'Content-Type': 'application/json' }
      }
    )

  } catch (error) {
    return new Response(
      JSON.stringify({ error: error.message }),
      {
        status: 500,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' }
      }
    )
  }
})
