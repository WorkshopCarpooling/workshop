<span class="user-avatar" aria-hidden="true">
  @if($avatarUser->avatar_path)
    <img src="{{ route('profile.avatar') }}" alt="">
  @else
    {{ mb_strtoupper(mb_substr($avatarUser->full_name, 0, 1)) }}
  @endif
</span>
