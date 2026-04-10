<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WoodColorReference;
use App\Models\WoodGrainProfile;
use App\Models\WoodReferenceImage;
use App\Models\WoodSmellProfile;
use App\Models\WoodSpecies;
use App\Rules\AllowedImageDimension;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SpeciesController extends Controller
{
    // ──────────────────────────────────────────────────
    // Species CRUD
    // ──────────────────────────────────────────────────

    public function index()
    {
        $species = WoodSpecies::withCount(['colorReferences', 'smellProfiles', 'referenceImages'])
            ->with('grainProfile')
            ->orderBy('name')
            ->get();

        return view('admin.species.index', compact('species'));
    }

    public function create()
    {
        return view('admin.species.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'local_name'      => 'nullable|string|max:100',
            'scientific_name' => 'nullable|string|max:150',
            'hardness'        => 'required|in:hardwood,softwood',
            'density_min'     => 'nullable|numeric|min:0',
            'density_max'     => 'nullable|numeric|min:0',
            'is_protected'    => 'boolean',
            'cites_appendix'  => 'nullable|in:I,II,III',
            'description'     => 'nullable|string|max:2000',
        ]);

        $data['is_protected'] = $request->boolean('is_protected');

        $species = WoodSpecies::create($data);

        return redirect()
            ->route('admin.species.show', $species)
            ->with('success', "Species \"{$species->name}\" created. Now add anatomy details below.");
    }

    public function show(WoodSpecies $species)
    {
        $species->load(['colorReferences', 'smellProfiles', 'grainProfile', 'referenceImages']);
        return view('admin.species.show', compact('species'));
    }

    public function edit(WoodSpecies $species)
    {
        return view('admin.species.edit', compact('species'));
    }

    public function update(Request $request, WoodSpecies $species)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'local_name'      => 'nullable|string|max:100',
            'scientific_name' => 'nullable|string|max:150',
            'hardness'        => 'required|in:hardwood,softwood',
            'density_min'     => 'nullable|numeric|min:0',
            'density_max'     => 'nullable|numeric|min:0',
            'is_protected'    => 'boolean',
            'cites_appendix'  => 'nullable|in:I,II,III',
            'description'     => 'nullable|string|max:2000',
        ]);

        $data['is_protected'] = $request->boolean('is_protected');
        $species->update($data);

        return redirect()
            ->route('admin.species.show', $species)
            ->with('success', "Species \"{$species->name}\" updated.");
    }

    public function destroy(WoodSpecies $species)
    {
        // Delete reference images from disk first
        foreach ($species->referenceImages as $img) {
            $this->deleteImageFile($img->image_path);
        }

        $species->delete();

        return redirect()
            ->route('admin.species.index')
            ->with('success', "Species \"{$species->name}\" and all related data deleted.");
    }

    // ──────────────────────────────────────────────────
    // Color References
    // ──────────────────────────────────────────────────

    public function storeColor(Request $request, WoodSpecies $species)
    {
        $request->validate([
            'hex'   => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'label' => 'required|in:heartwood,sapwood,aged,fresh_cut',
            'notes' => 'nullable|string|max:300',
        ]);

        WoodColorReference::create([
            'species_id' => $species->id,
            'hex'        => strtolower($request->hex),
            'label'      => $request->label,
            'notes'      => $request->notes,
            'created_at' => now(),
        ]);

        return back()->with('success', "Color reference added.");
    }

    public function destroyColor(WoodSpecies $species, WoodColorReference $color)
    {
        abort_if($color->species_id !== $species->id, 403);
        $color->delete();
        return back()->with('success', "Color reference removed.");
    }

    // ──────────────────────────────────────────────────
    // Smell Profiles
    // ──────────────────────────────────────────────────

    public function storeSmell(Request $request, WoodSpecies $species)
    {
        $request->validate([
            'smell'     => 'required|in:aromatic,resinous,sweet,bitter,musty,odorless,spicy,sour',
            'intensity' => 'required|in:faint,moderate,strong',
            'notes'     => 'nullable|string|max:300',
        ]);

        WoodSmellProfile::create([
            'species_id' => $species->id,
            'smell'      => $request->smell,
            'intensity'  => $request->intensity,
            'notes'      => $request->notes,
        ]);

        return back()->with('success', "Smell profile added.");
    }

    public function destroySmell(WoodSpecies $species, WoodSmellProfile $smell)
    {
        abort_if($smell->species_id !== $species->id, 403);
        $smell->delete();
        return back()->with('success', "Smell profile removed.");
    }

    // ──────────────────────────────────────────────────
    // Grain Profile
    // ──────────────────────────────────────────────────

    public function storeGrain(Request $request, WoodSpecies $species)
    {
        $request->validate([
            'grain_pattern'   => 'nullable|in:straight,wavy,interlocked,irregular',
            'pore_type'       => 'nullable|in:ring_porous,diffuse_porous,closed',
            'ring_visibility' => 'nullable|in:strong,faint,none',
            'ray_visibility'  => 'nullable|in:visible,not_visible',
            'surface_texture' => 'nullable|in:smooth,rough',
            'special_figure'  => 'nullable|in:none,curly,ribbon,burl,flame',
            'notes'           => 'nullable|string|max:500',
        ]);

        WoodGrainProfile::updateOrCreate(
            ['species_id' => $species->id],
            array_merge($request->only([
                'grain_pattern', 'pore_type', 'ring_visibility',
                'ray_visibility', 'surface_texture', 'special_figure', 'notes',
            ]), ['species_id' => $species->id])
        );

        return back()->with('success', "Grain profile saved.");
    }

    // ──────────────────────────────────────────────────
    // Reference Images
    // ──────────────────────────────────────────────────

    public function storeImage(Request $request, WoodSpecies $species)
    {
        $request->validate([
            'image'      => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:8192', new AllowedImageDimension],
            'cut_type'   => 'required|in:cross_section,side_cut,flat_cut',
            'label'      => 'nullable|string|max:100',
            'is_primary' => 'boolean',
        ]);

        $file     = $request->file('image');
        $ext      = $file->getClientOriginalExtension();
        $filename = 'ref_' . Str::slug($species->name) . '_' . Str::uuid() . '.' . $ext;
        $path     = $file->storeAs('species/reference', $filename, 'public');

        // If marked as primary, unset previous primary for this cut_type
        if ($request->boolean('is_primary')) {
            WoodReferenceImage::where('species_id', $species->id)
                ->where('cut_type', $request->cut_type)
                ->update(['is_primary' => false]);
        }

        WoodReferenceImage::create([
            'species_id' => $species->id,
            'image_path' => $path,
            'cut_type'   => $request->cut_type,
            'label'      => $request->label,
            'is_primary' => $request->boolean('is_primary'),
            'created_at' => now(),
        ]);

        return back()->with('success', "Reference image uploaded.");
    }

    public function destroyImage(WoodSpecies $species, WoodReferenceImage $image)
    {
        abort_if($image->species_id !== $species->id, 403);
        $this->deleteImageFile($image->image_path);
        $image->delete();
        return back()->with('success', "Reference image deleted.");
    }

    public function setPrimaryImage(WoodSpecies $species, WoodReferenceImage $image)
    {
        abort_if($image->species_id !== $species->id, 403);

        WoodReferenceImage::where('species_id', $species->id)
            ->where('cut_type', $image->cut_type)
            ->update(['is_primary' => false]);

        $image->update(['is_primary' => true]);

        return back()->with('success', "Set as primary image.");
    }

    // ──────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────

    private function deleteImageFile(string $path): void
    {
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
