/** @typedef {import("../dto/creatorDto.js").CreatorDto} CreatorDto */

/** @param {CreatorDto} dto */
export function toCreator(dto) {
  return {
    id: dto.id,
    displayName: dto.display_name,
    description: dto.description,
    avatarUrl: dto.avatar_url,
    schoolName: dto.school_name,
  };
}

/** @param {ReturnType<typeof toCreator>} creator @returns {CreatorDto} */
export function toCreatorDto(creator) {
  return {
    id: creator.id,
    display_name: creator.displayName,
    description: creator.description,
    avatar_url: creator.avatarUrl,
    school_name: creator.schoolName,
  };
}
