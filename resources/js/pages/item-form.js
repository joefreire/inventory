import { PhotoPicker } from '../components/photo-picker';
import { TagInput } from '../components/tag-input';

/** Edit page: photos and tags, no AI. */
export default function init() {
    const picker = document.getElementById('photoPicker');
    if (picker) new PhotoPicker(picker);
    const tags = document.getElementById('tags');
    if (tags) new TagInput(tags);
}
